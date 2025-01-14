<?php

namespace MediaWiki\Extension\Description2;

use Parser;
use PPFrame;

class Description2 {
	private const TRUNCATION_MARKER = '&hellip;';

	/**
	 * @param Parser $parser
	 * @param string $description
	 */
	public static function setDescription( Parser $parser, string $description ): void {
		$parserOutput = $parser->getOutput();

		// if the description page property doesn't exist yet
		// and if the description isn't an empty string
		if ( $parserOutput->getPageProperty( 'description' ) === null && $description !== '' ) {
			// set the description page property
			$parserOutput->setPageProperty( 'description', $description );
		}
	}

	/**
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param string[] $args
	 * @return string
	 */
	public static function onParserFunction( Parser $parser, PPFrame $frame, array $args ): string {
		// if a description is given
		if ( isset( $args[0] ) ) {
			// set the description
			self::setDescription( $parser, $frame->expand( $args[0] ) );
		}
		// return nothing (no rendered output)
		return '';
	}

	/**
	 * Truncates a string to a specific amount of characters while preserving words.
	 *
	 * Modified from <https://stackoverflow.com/a/79986>.
	 *
	 * @param string $text Plain text
	 * @param int $requestedLength Maximum number of characters
	 * @return string Truncated text
	 */
	public static function truncate( string $text, int $requestedLength ): string {
		// sanity checks
		if ( $requestedLength <= 0 ) {
			return '';
		}
		$length = mb_strlen( $text );
		if ( $length <= $requestedLength ) {
			return $text;
		}

		// account for the truncation marker
		$requestedLength = $requestedLength - strlen( self::TRUNCATION_MARKER );

		$parts = preg_split( '/([\s\n\r]+)/', $text, flags: PREG_SPLIT_DELIM_CAPTURE );
		$parts_count = count( $parts );

		$length = 0;
		$last_part = 0;
		for ( ; $last_part < $parts_count; ++$last_part ) {
			$length += mb_strlen( $parts[$last_part] );
			if ( $length > $requestedLength ) {
				break;
			}
		}

		return trim( implode( array_slice( $parts, 0, $last_part ) ) ) . self::TRUNCATION_MARKER;
	}
}
