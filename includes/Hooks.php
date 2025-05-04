<?php

namespace MediaWiki\Extension\Description2;

use MediaWiki\Api\Hook\ApiOpenSearchSuggestHook;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Page\PageProps;
use MediaWiki\Parser\Parser;
use MediaWiki\Rest\Hook\SearchResultProvideDescriptionHook;

class Hooks implements
	ParserAfterTidyHook,
	ParserFirstCallInitHook,
	OutputPageParserOutputHook,
	ApiOpenSearchSuggestHook,
	SearchResultProvideDescriptionHook
{
	private Config $config;
	private PageProps $pageProps;
	private DescriptionProvider $descriptionProvider;
	private int $maximumLength;

	public function __construct(
		ConfigFactory $configFactory,
		PageProps $pageProps,
	) {
		$this->config = $configFactory->makeConfig( 'description2' );
		$this->pageProps = $pageProps;
		$this->descriptionProvider = new DescriptionProvider( $this->config->get( 'Description2IgnoreSelectors' ) );
		$this->maximumLength = $this->config->get( 'Description2MaximumLength' );
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ): void {
		if ( $this->config->get( 'Description2EnableParserFunction' ) ) {
			// parser function is enabled
			$parser->setFunctionHook(
				'description2',
				[ Description2::class, 'onParserFunction' ],
				Parser::SFH_OBJECT_ARGS
			);
		}
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterTidy
	 * @inheritDoc
	 */
	public function onParserAfterTidy( $parser, &$text ): void {
		$parserOutput = $parser->getOutput();

		// avoid running for interface messages
		if ( $parser->getOptions()->getInterfaceMessage() ) {
			return;
		}

		// avoid running if there's already a description
		$description = $parserOutput->getPageProperty( 'description' );
		if ( $description ) {
			return;
		}

		// extract the description
		$description = $this->descriptionProvider->extractDescription( $text );
		// if the maximum length is set
		if ( $this->maximumLength > 0 ) {
			// limit the length
			$description = Description2::truncate( $description, $this->maximumLength );
		}

		// set the description
		Description2::setDescription( $parser, $description );
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 * @inheritDoc
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		// add the description into the page's metadata
		$description = $parserOutput->getPageProperty( 'description' );
		if ( $description !== null ) {
			$outputPage->addMeta( 'description', $description );
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ApiOpenSearchSuggest
	 * @inheritDoc
	 */
	public function onApiOpenSearchSuggest( &$results ): void {
		$titles = array_map( static fn ( $result ) => $result['title'], $results );
		foreach ( self::getDescriptions( $titles ) as $id => $description ) {
			if ( $description === null ) {
				continue;
			}

			$results[$id]['extract'] = $description;
			$results[$id]['extract trimmed'] = false;
		}
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/SearchResultProvideDescription
	 * @inheritDoc
	 */
	public function onSearchResultProvideDescription( array $pageIdentities, &$descriptions ): void {
		foreach ( self::getDescriptions( $pageIdentities ) as $id => $description ) {
			$descriptions[$id] = $description;
		}
	}

	private function getDescriptions( array $titles ): array {
		return $this->pageProps->getProperties( $titles, 'description' );
	}
}
