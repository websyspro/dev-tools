<?php

namespace Websyspro\DevTools\Enums;

enum MimeType: string
{
	case JPG = 'image/jpeg';
	case PNG = 'image/png';
	case GIF = 'image/gif';
	case WEBP = 'image/webp';
	case SVG = 'image/svg+xml';
	case PDF = 'application/pdf';
	case JS = 'application/javascript';
	case CSS = 'text/css';
	case WOFF = 'font/woff';
	case WOFF2 = 'font/woff2';
	case TTF = 'font/ttf';
	case EOT = 'application/vnd.ms-fontobject';
	case ICO = 'image/x-icon';

	public static function fromExtension(
		string $ext
	): MimeType|null {
		return match(strtolower( $ext )){
			'jpg' => MimeType::JPG,
			'jpeg' => MimeType::JPG,
			'png' => MimeType::PNG,
			'gif' => MimeType::GIF,
			'webp' => MimeType::WEBP,
			'svg' => MimeType::SVG,
			'pdf' => MimeType::PDF,
			'js' => MimeType::JS,
			'css' => MimeType::CSS,
			'woff' => MimeType::WOFF,
			'woff2' => MimeType::WOFF2,
			'ttf' => MimeType::TTF,
			'eot' => MimeType::EOT,
			'ico' => MimeType::ICO,
				default => null
		};
	}
}
