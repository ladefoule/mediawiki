<?php
/**
 * Implements Special:ComparePages
 *
 * Copyright © 2010 Derk-Jan Hartman <hartman@videolan.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

/**
 * Implements Special:ComparePages
 *
 * @ingroup SpecialPage
 */
class SpecialComparePages extends SpecialPage {

	// Stored objects
	protected $opts, $skin;

	// Some internal settings
	protected $showNavigation = false;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param IContentHandlerFactory $contentHandlerFactory
	 */
	public function __construct(
		RevisionLookup $revisionLookup,
		IContentHandlerFactory $contentHandlerFactory
	) {
		parent::__construct( 'ComparePages' );
		$this->revisionLookup = $revisionLookup;
		$this->contentHandlerFactory = $contentHandlerFactory;
	}

	/**
	 * Show a form for filtering namespace and username
	 *
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->addModuleStyles( 'mediawiki.special' );
		$this->addHelpLink( 'Help:Diff' );

		$form = HTMLForm::factory( 'ooui', [
			'Page1' => [
				'type' => 'title',
				'name' => 'page1',
				'label-message' => 'compare-page1',
				'size' => '40',
				'section' => 'page1',
				'validation-callback' => [ $this, 'checkExistingTitle' ],
				'required' => false,
			],
			'Revision1' => [
				'type' => 'int',
				'name' => 'rev1',
				'label-message' => 'compare-rev1',
				'size' => '8',
				'section' => 'page1',
				'validation-callback' => [ $this, 'checkExistingRevision' ],
			],
			'Page2' => [
				'type' => 'title',
				'name' => 'page2',
				'label-message' => 'compare-page2',
				'size' => '40',
				'section' => 'page2',
				'validation-callback' => [ $this, 'checkExistingTitle' ],
				'required' => false,
			],
			'Revision2' => [
				'type' => 'int',
				'name' => 'rev2',
				'label-message' => 'compare-rev2',
				'size' => '8',
				'section' => 'page2',
				'validation-callback' => [ $this, 'checkExistingRevision' ],
			],
			'Action' => [
				'type' => 'hidden',
				'name' => 'action',
			],
			'Diffonly' => [
				'type' => 'hidden',
				'name' => 'diffonly',
			],
			'Unhide' => [
				'type' => 'hidden',
				'name' => 'unhide',
			],
		], $this->getContext(), 'compare' );
		$form->setSubmitTextMsg( 'compare-submit' );
		$form->suppressReset();
		$form->setMethod( 'get' );
		$form->setSubmitCallback( [ $this, 'showDiff' ] );

		$form->loadData();
		$form->displayForm( '' );
		$form->trySubmit();
	}

	/**
	 * @internal Callback for HTMLForm
	 * @param array $data
	 * @param HTMLForm $form
	 */
	public function showDiff( $data, HTMLForm $form ) {
		$rev1 = $this->revOrTitle( $data['Revision1'], $data['Page1'] );
		$rev2 = $this->revOrTitle( $data['Revision2'], $data['Page2'] );

		if ( $rev1 && $rev2 ) {
			$revisionRecord = $this->revisionLookup->getRevisionById( $rev1 );

			if ( $revisionRecord ) { // NOTE: $rev1 was already checked, should exist.
				$contentModel = $revisionRecord->getSlot(
					SlotRecord::MAIN,
					RevisionRecord::RAW
				)->getModel();
				$contentHandler = $this->contentHandlerFactory->getContentHandler( $contentModel );
				$de = $contentHandler->createDifferenceEngine( $form->getContext(),
					$rev1,
					$rev2,
					null, // rcid
					( $data['Action'] == 'purge' ),
					( $data['Unhide'] == '1' )
				);
				$de->showDiffPage( true );
			}
		}
	}

	private function revOrTitle( $revision, $title ) {
		if ( $revision ) {
			return $revision;
		} elseif ( $title ) {
			$title = Title::newFromText( $title );
			if ( $title instanceof Title ) {
				return $title->getLatestRevID();
			}
		}

		return null;
	}

	/**
	 * @internal Callback for HTMLForm
	 * @param string|null $value
	 * @param array $alldata
	 * @return string|bool
	 */
	public function checkExistingTitle( $value, $alldata ) {
		if ( $value === '' || $value === null ) {
			return true;
		}
		$title = Title::newFromText( $value );
		if ( !$title instanceof Title ) {
			return $this->msg( 'compare-invalid-title' )->parseAsBlock();
		}
		if ( !$title->exists() ) {
			return $this->msg( 'compare-title-not-exists' )->parseAsBlock();
		}

		return true;
	}

	/**
	 * @internal Callback for HTMLForm
	 * @param string|null $value
	 * @param array $alldata
	 * @return string|bool
	 */
	public function checkExistingRevision( $value, $alldata ) {
		if ( $value === '' || $value === null ) {
			return true;
		}
		$revisionRecord = $this->revisionLookup->getRevisionById( $value );
		if ( $revisionRecord === null ) {
			return $this->msg( 'compare-revision-not-exists' )->parseAsBlock();
		}

		return true;
	}

	protected function getGroupName() {
		return 'pagetools';
	}
}
