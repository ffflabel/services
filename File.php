<?php

namespace FFFlabel\Services;

class File {
	private $path;

	public function __construct( $path ) {
		if ( ! file_exists( $path ) ) {
			throw new \InvalidArgumentException( "file does not exists ($path)" );
		}
		$this->path = $path;
	}

	public function get_content() {
		return file_get_contents( $this->path );
	}

	public function __toString() {
		return $this->path;
	}
}
