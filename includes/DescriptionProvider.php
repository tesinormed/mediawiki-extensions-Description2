<?php

namespace MediaWiki\Extension\Description2;

use Wikimedia\RemexHtml\HTMLData;
use Wikimedia\RemexHtml\Serializer\HtmlFormatter;
use Wikimedia\RemexHtml\Serializer\Serializer;
use Wikimedia\RemexHtml\Serializer\SerializerNode;
use Wikimedia\RemexHtml\Tokenizer\Tokenizer;
use Wikimedia\RemexHtml\TreeBuilder\Dispatcher;
use Wikimedia\RemexHtml\TreeBuilder\TreeBuilder;

class DescriptionProvider extends HtmlFormatter {
	/** @var string[] */
	private array $ignoreSelectors;

	/**
	 * @param string[] $ignoreSelectors
	 */
	public function __construct( array $ignoreSelectors ) {
		parent::__construct();
		$this->ignoreSelectors = $ignoreSelectors;
	}

	/**
	 * Skips document starter tags.
	 *
	 * @param ?string $fragmentNamespace
	 * @param ?string $fragmentName
	 * @return string
	 */
	public function startDocument( $fragmentNamespace, $fragmentName ): string {
		return '';
	}

	/**
	 * Skips comments.
	 *
	 * @param SerializerNode $parent
	 * @param string $text
	 * @return string
	 */
	public function comment( SerializerNode $parent, $text ): string {
		return '';
	}

	/**
	 * Ignores specific HTML tags and then removes the remaining HTML tags to extract the text.
	 *
	 * @param SerializerNode $parent
	 * @param SerializerNode $node
	 * @param ?string $contents
	 * @return string
	 */
	public function element( SerializerNode $parent, SerializerNode $node, $contents ): string {
		// get the node's classes
		$nodeClasses = $node->attrs->getValues()['class'] ?? null;
		if ( $nodeClasses !== null ) {
			// turn this into an array
			$nodeClasses = explode( ' ', $nodeClasses );
		} else {
			$nodeClasses = [];
		}

		// for each of the ignore selectors
		foreach ( $this->ignoreSelectors as $ignoreSelector ) {
			// get the tag name and the class name
			@[ $tagName, $className ] = explode( '.', $ignoreSelector );

			// if the tag name doesn't match, continue
			if ( $tagName !== null && $node->name !== $tagName ) {
				continue;
			}

			// if the class name doesn't match, continue
			if ( $className !== null && !in_array( $className, $nodeClasses ) ) {
				continue;
			}

			// one of the above matched, ignore this tag
			return '';
		}

		// none of the ignore selectors matched, return this tag's contents
		return $contents ?? '';
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function extractDescription( string $text ): string {
		$serializer = new Serializer( $this );
		$treeBuilder = new TreeBuilder( $serializer );
		$dispatcher = new Dispatcher( $treeBuilder );
		$tokenizer = new Tokenizer( $dispatcher, $text );

		$tokenizer->execute( [
			'fragmentNamespace' => HTMLData::NS_HTML,
			'fragmentName' => 'body',
		] );

		return trim( $serializer->getResult() );
	}
}
