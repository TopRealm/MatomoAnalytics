<?php

namespace Miraheze\MatomoAnalytics\HookHandlers;

use MediaWiki\Extension\CookieConsent\CookiePreferences;
use MediaWiki\Hook\InfoActionHook;
use MediaWiki\Hook\SkinAfterBottomScriptsHook;
use MediaWiki\Html\Html;
use MediaWiki\WikiMap\WikiMap;
use Miraheze\MatomoAnalytics\ConfigNames;
use Miraheze\MatomoAnalytics\MatomoAnalytics;
use Miraheze\MatomoAnalytics\MatomoAnalyticsWiki;

class Main implements
	InfoActionHook,
	SkinAfterBottomScriptsHook
{

	/** @var string|null */
	private ?string $searchTerm = null;

	/** @var string|null */
	private ?string $searchProfile = null;

	/** @var int|null */
	private ?int $searchCount = null;

	public function __construct(
		private readonly ?CookiePreferences $cookiePreferences
	) {
	}

	/**
	 * Hook to save some data in Special:Search.
	 *
	 * @param string $term Searched term.
	 * @param mixed $titleMatches Results in the titles.
	 * @param mixed $textMatches Results in the fulltext.
	 * @return true
	 */
	public function onSpecialSearchResults( $term, $titleMatches, $textMatches ) {
		$this->searchTerm = $term;
		$this->searchCount = 0;

		if ( is_object( $titleMatches ) && method_exists( $titleMatches, 'numRows' ) ) {
			$this->searchCount += (int)$titleMatches->numRows();
		}
		if ( is_object( $textMatches ) && method_exists( $textMatches, 'numRows' ) ) {
			$this->searchCount += (int)$textMatches->numRows();
		}

		return true;
	}

	/**
	 * Hook to save some data in Special:Search.
	 *
	 * @param mixed $search Special page.
	 * @param string|null $profile Search profile.
	 * @param mixed $engine Search engine.
	 * @return true
	 */
	public function onSpecialSearchSetupEngine( $search, $profile, $engine ) {
		$this->searchProfile = $profile !== null ? (string)$profile : null;
		return true;
	}

	/**
	 * Add Matomo JS to all MediaWiki pages
	 * Exclude users with the 'noanalytics' userright
	 *
	 * @inheritDoc
	 */
	public function onSkinAfterBottomScripts( $skin, &$text ) {
		$config = $skin->getConfig();
		// Check if JS tracking is disabled and bow out early
		if ( $config->get( ConfigNames::DisableJS ) ) {
			return;
		}

		if ( $this->cookiePreferences !== null ) {
			$category = CookiePreferences::COOKIES_STATISTICS;
			$isGranted = $this->cookiePreferences->isConsentGranted( $category );
			if ( !$isGranted ) {
				return;
			}
		}

				if ( $config->get( ConfigNames::IgnoreBots ) && $skin->getAuthority()->isAllowed( 'bot' ) ) {
			$text .= '<!-- ' . wfMessage( 'matomoanalytics-comment-bot-disabled' )->plain() . ' -->';
			return;
		}

		if ( $skin->getAuthority()->isAllowed( 'noanalytics' ) ) {
			$text .= '<!-- ' . wfMessage( 'matomoanalytics-comment-noanalytics-assigned' )->plain() . ' -->';
			return;
		}


		$siteId = (string)MatomoAnalytics::getSiteID( WikiMap::getCurrentWikiId(), disableCache: false );

		$globalId = $config->get( ConfigNames::GlobalID );
		$globalIdString = (string)$globalId;

		$serverUrl = $config->get( ConfigNames::ServerURL );
		$title = $skin->getRelevantTitle();

		$jsTitle = Html::encodeJsVar( $title->getPrefixedText() );
		$wikiId = Html::encodeJsVar( WikiMap::getCurrentWikiId() );

		$urlTitle = $title->getPrefixedURL();
		$userType = $skin->getUser()->isRegistered() ? 'User' : 'Anonymous';

		$disableCookie = (int)$config->get( ConfigNames::DisableCookie );
		$forceGetRequest = (int)$config->get( ConfigNames::ForceGetRequest );
		$enableCustomDimensionsUserType = (int)$config->get( ConfigNames::EnableCustomDimensionsUserType );

		$usernameTracking = '';
		if ( $config->get( ConfigNames::TrackUsernames ) && $skin->getUser()->isRegistered() ) {
			$jsUsername = Html::encodeJsVar( $skin->getUser()->getName() );
			$usernameTracking = "_paq.push(['setUserId', {$jsUsername}]);";
		}

		$searchTracking = '';
		if ( $this->searchTerm !== null ) {
			$searchTerm = Html::encodeJsVar( $this->searchTerm );
			$searchProfile = $this->searchProfile !== null ? Html::encodeJsVar( $this->searchProfile ) : 'false';
			$searchCount = $this->searchCount !== null ? (string)$this->searchCount : 'false';
			$searchTracking = "_paq.push(['trackSiteSearch', {$searchTerm}, {$searchProfile}, {$searchCount}]);";
		}

		$text .= <<<SCRIPT
			<script>
			var _paq = window._paq = window._paq || [];
			if ( {$disableCookie} ) {
				_paq.push(['disableCookies']);
			}
			if ( {$forceGetRequest} ) {
				_paq.push(['setRequestMethod', 'GET']);
			}
			{$usernameTracking}
			{$searchTracking}
			_paq.push(['trackPageView']);
			_paq.push(['enableLinkTracking']);
			(function() {
				var u = "{$serverUrl}";
				_paq.push(['setTrackerUrl', u+'matomo.php']);
				_paq.push(['setDocumentTitle', {$wikiId} + " - " + {$jsTitle}]);
				_paq.push(['setSiteId', {$siteId}]);
				if ( {$enableCustomDimensionsUserType} ) {
					_paq.push(['setCustomDimension', 1, "{$userType}"]);
				}
				if ( {$globalId} ) {
					_paq.push(['addTracker', u + 'matomo.php', {$globalIdString}]);
				}
				var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
				g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
			})();
			</script>
			<noscript><p><img src="{$serverUrl}matomo.php?idsite={$siteId}&amp;rec=1&amp;action_name={$urlTitle}" style="border: 0;" alt="" /></p></noscript>
		SCRIPT;
	}


	/**
	 * Display total pageviews in the last 30 days and show a graph with details when clicked.
	 *
	 * @inheritDoc
	 */
	public function onInfoAction( $context, &$pageInfo ) {
		$mAId = MatomoAnalytics::getSiteID( WikiMap::getCurrentWikiId(), disableCache: false );
		$mA = new MatomoAnalyticsWiki( period: 30, siteId: $mAId );

		$title = $context->getTitle();
		$url = $title->getFullURL();
		$data = $mA->getPageViews( $url );
		$total = array_sum( $data );

		$pageInfo['header-basic'][] = [
			$context->msg( 'matomoanalytics-labels-pastmonth' ),
			$context->msg( 'matomoanalytics-count' )->numParams( $total )->parse()
		];
	}
}
