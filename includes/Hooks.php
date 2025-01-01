<?php

namespace MediaWiki\Extension\Description2;

use Config;
use ConfigFactory;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use Parser;

class Hooks implements
	ParserAfterTidyHook,
	ParserFirstCallInitHook,
	OutputPageParserOutputHook
{
	/** @var Config */
	private Config $config;

	/** @var DescriptionProvider */
	private DescriptionProvider $descriptionProvider;

	/** @var int */
	private int $maximumLength;

	/**
	 * @param ConfigFactory $configFactory
	 */
	public function __construct(
		ConfigFactory $configFactory
	) {
		$this->config = $configFactory->makeConfig( 'description2' );
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
}
