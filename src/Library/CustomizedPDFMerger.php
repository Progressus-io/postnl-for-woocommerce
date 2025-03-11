<?php
/**
 * Class CustomizedPDFMerger file.
 *
 * @package PostNLWooCommerce\Library
 */

namespace PostNLWooCommerce\Library;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;

class CustomizedPDFMerger {

	/**
	 * Files to merge.
	 *
	 * @var array
	 */
	private $_files;    //['form.pdf']  ["1,2,4, 5-19"]

	/**
	 * Settings class instance.
	 *
	 * @var Settings
	 */
	protected $settings;

	public function __construct() {
		$this->settings = Settings::get_instance();
	}

	/**
	 * Add a PDF for inclusion in the merge with a valid file path. Pages should be formatted: 1,3,6, 12-16.
	 *
	 * @param $filepath
	 * @param string $pages
	 * @param string|null $orientation
	 *
	 * @return CustomizedPDFMerger
	 * @throws Exception
	 */
	public function addPDF( $filepath, $pages = 'all', $orientation = null ) {
		if ( file_exists( $filepath ) ) {
			if ( strtolower( $pages ) != 'all' ) {
				$pages = $this->_rewritepages( $pages );
			}

			$this->_files[] = array( $filepath, $pages, $orientation );
		} else {
			throw new Exception( "Could not locate PDF on '$filepath'" );
		}

		return $this;
	}

	/**
	 * Merges your provided PDFs and outputs to specified location.
	 *
	 * @param string $outputmode
	 * @param string $outputpath
	 * @param string $orientation
	 * @param string $start_position
	 *
	 * @return string|bool
	 * @throws Exception
	 */
	public function merge( $outputmode = 'browser', $outputpath = 'newfile.pdf', $orientation = 'A', $start_position = 'top-left' ) {
		if ( ! isset( $this->_files ) || ! is_array( $this->_files ) ) {
			throw new Exception( "No PDFs to merge." );
		}

		$fpdi  = new PDF_Rotate();
		$files = array();

		// merger operations
		foreach ( $this->_files as $file ) {
			$filename        = $file[0];
			$filepages       = $file[1];
			$fileorientation = ( ! is_null( $file[2] ) ) ? $file[2] : $orientation;

			$count = $fpdi->setSourceFile( $filename );

			//add the pages
			if ( $filepages == 'all' ) {
				for ( $i = 1; $i <= $count; $i ++ ) {
					$template = $fpdi->importPage( $i );
					$size     = $fpdi->getTemplateSize( $template );
					if ( $fileorientation === 'A' ) {
						$fileorientation = ( $size['width'] > $size['height'] ) ? 'L' : 'P';
					}

					$files[ $filename ][] = array(
						'template'    => $template,
						'size'        => $size,
						'orientation' => $fileorientation,
					);
				}
			} else {
				foreach ( $filepages as $page ) {
					if ( ! $template = $fpdi->importPage( $page ) ) {
						throw new Exception( "Could not load page '$page' in PDF '$filename'. Check that the page exists." );
					}
					$size = $fpdi->getTemplateSize( $template );

					$files[ $filename ][] = array(
						'template'    => $template,
						'size'        => $size,
						'orientation' => $fileorientation,
					);
				}
			}
		}

		$label_number   = 1;
		$a4_size        = Utils::get_paper_size( 'A4' );
		$a6_size        = Utils::get_paper_size( 'A6' );

		$label_format   = $this->settings->get_label_format();
		$first_page     = true;
		$coordinate_map = array(
			'top-left'     => array(
				1 => array( 0, 0 ),
				2 => array( 148, 0 ),
				3 => array( 0, 105 ),
				4 => array( 148, 105 ),
			),
			'top-right'    => array(
				1 => array( 148, 0 ),
				2 => array( 0, 105 ),
				3 => array( 148, 105 ),
				4 => array( 148, 0 ), // this will be on a new page
			),
			'bottom-left'  => array(
				1 => array( 0, 105 ),
				2 => array( 148, 105 ),
				3 => array( 0, 105 ), // this will be on a new page
				4 => array( 148, 0 ), // this will be on a new page
			),
			'bottom-right' => array(
				1 => array( 148, 105 ),
				2 => array( 0, 0 ), // this will be on a new page
				3 => array( 148, 0 ), // this will be on a new page
				4 => array( 0, 105 ), // this will be on a new page
			),
		);

		$new_page_condition_map = array(
			'top-left'     => 4,
			'top-right'    => 3,
			'bottom-left'  => 2,
			'bottom-right' => 1,
		);

		foreach ( $files as $filename => $file_templates ) {
			foreach ( $file_templates as $file_template ) {
				$rotation_needed  = false;
				$tolerance        = 5; // Tolerance in mm for A6 only.
				$file_width       = intval( $file_template['size']['width'] );
				$file_height      = intval( $file_template['size']['height'] );
				$file_orientation = $file_template['size']['orientation'];
				$is_cn23          = ( stripos( $filename, '-cn23-' ) !== false );

				// Check if the file matches A6 dimensions (vertical or horizontal) within tolerance.
				$isA6 = ( abs( $file_width - intval( $a6_size['height'] ) ) <= $tolerance &&
				          abs( $file_height - intval( $a6_size['width'] ) ) <= $tolerance )
				        || ( abs( $file_width - intval( $a6_size['width'] ) ) <= $tolerance &&
				             abs( $file_height - intval( $a6_size['height'] ) ) <= $tolerance );

				if ( 'A6' === $label_format || $is_cn23 || ! $isA6 || 1 === count( $files ) ) {
					$fpdi->AddPage( $file_orientation, array(
						$file_width,
						$file_height,
					) );
					$fpdi->useTemplate( $file_template['template'] );
					$label_number = 1;
					continue;
				}

				if (
					$file_width <= ( intval( $a6_size['height'] ) + $tolerance ) &&
					$file_height <= ( intval( $a6_size['width'] ) + $tolerance )
				) {
					$rotation_needed = true;
				}

				$new_page_condition = $new_page_condition_map[ $start_position ];

				if ( 1 === $label_number % $new_page_condition || $start_position == 'bottom-right' ) {
					$fpdi->AddPage( 'L', array(
						$a4_size['width'],
						$a4_size['height']
					) );
					$label_number = 1;

					if ( $first_page ) {
						// If it's the first page, use the given start_position.
						$first_page = false;
					} else {
						// For other pages, always start from top-left.
						$start_position = 'top-left';
					}
				}

				$coords = $coordinate_map[ $start_position ][ $label_number ];

				// Scale A6 PDF.
				if ( $rotation_needed ) {
					$scale_x = $a6_size['width'] / $file_height;
					$scale_y = $a6_size['height'] / $file_width;
				} else {
					$scale_x = $a6_size['width'] / $file_width;
					$scale_y = $a6_size['height'] / $file_height;
				}

				$scale = min( $scale_x, $scale_y );

				if ( $rotation_needed ) {
					$fpdi->Rotate( 90, 0, 0 );
					$fpdi->useTemplate( $file_template['template'], - $file_template['size']['width'] - $coords[1], $coords[0], ( $file_template['size']['width'] * $scale ), ( $file_template['size']['height'] * $scale ) );

					$fpdi->Rotate( 0 ); // Reset rotation
				} else {
					// Portrait - place as is
					$fpdi->useTemplate( $file_template['template'], $coords[0], $coords[1], ( $file_template['size']['width'] * $scale ), ( $file_template['size']['height'] * $scale ), false );
				}

				$label_number ++;
			}
		}

		//output operations
		$mode = $this->_switchmode( $outputmode );

		if ( $mode == 'S' ) {
			return $fpdi->Output( $outputpath, 'S' );
		} else {
			if ( $fpdi->Output( $outputpath, $mode ) == '' ) {
				return true;
			} else {
				throw new Exception( "Error outputting PDF to '$outputmode'." );

				return false;
			}
		}


	}

	/**
	 * FPDI uses single characters for specifying the output location. Change our more descriptive string into proper format.
	 *
	 * @param $mode
	 *
	 * @return string Character
	 */
	private function _switchmode( $mode ) {
		switch ( strtolower( $mode ) ) {
			case 'download':
				return 'D';
				break;
			case 'file':
				return 'F';
				break;
			case 'string':
				return 'S';
				break;
			default:
				return 'I';
				break;
		}
	}

	/**
	 * Takes our provided pages in the form of 1,3,4,16-50 and creates an array of all pages
	 *
	 * @param $pages
	 *
	 * @return array
	 * @throws Exception
	 */
	private function _rewritepages( $pages ) {
		$pages = str_replace( ' ', '', $pages );
		$part  = explode( ',', $pages );

		//parse hyphens
		foreach ( $part as $i ) {
			$ind = explode( '-', $i );

			if ( count( $ind ) == 2 ) {
				$x = $ind[0]; //start page
				$y = $ind[1]; //end page

				if ( $x > $y ) {
					throw new Exception( "Starting page, '$x' is greater than ending page '$y'." );

					return false;
				}

				//add middle pages
				while ( $x <= $y ) {
					$newpages[] = (int) $x;
					$x ++;
				}
			} else {
				$newpages[] = (int) $ind[0];
			}
		}

		return $newpages;
	}
}
