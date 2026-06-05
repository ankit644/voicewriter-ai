/**
 * VoiceWriter AI — block editor sidebar.
 *
 * No build step: uses the WordPress-provided global packages (wp.element,
 * wp.components, wp.editPost, wp.plugins, wp.data, wp.blocks, wp.apiFetch).
 *
 * @package VoiceWriter_AI
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var Fragment = wp.element.Fragment;

	var PanelBody = wp.components.PanelBody;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;

	var cfg = window.voicewriterAI || {};

	/**
	 * Append a block of text to the editor as paragraph blocks.
	 *
	 * @param {string} text Generated text.
	 */
	function insertAsBlocks( text ) {
		var paragraphs = text.split( /\n{2,}/ ).filter( function ( p ) {
			return p.trim() !== '';
		} );
		var blocks = paragraphs.map( function ( p ) {
			return wp.blocks.createBlock( 'core/paragraph', { content: p.trim() } );
		} );
		if ( blocks.length ) {
			wp.data.dispatch( 'core/block-editor' ).insertBlocks( blocks );
		}
	}

	/**
	 * Read the current post's plain-text content for repurposing.
	 *
	 * @return {string} Plain text.
	 */
	function currentContent() {
		var content = wp.data.select( 'core/editor' ).getEditedPostContent();
		var tmp = document.createElement( 'div' );
		tmp.innerHTML = content;
		return ( tmp.textContent || tmp.innerText || '' ).trim();
	}

	/**
	 * The sidebar panel component.
	 *
	 * @return {Object} Element tree.
	 */
	function Panel() {
		var topicState = useState( '' );
		var topic = topicState[ 0 ];
		var setTopic = topicState[ 1 ];

		var busyState = useState( '' ); // '', 'train', 'generate', 'repurpose'
		var busy = busyState[ 0 ];
		var setBusy = busyState[ 1 ];

		var noticeState = useState( null ); // { status, text }
		var notice = noticeState[ 0 ];
		var setNotice = noticeState[ 1 ];

		var hasVoiceState = useState( !! cfg.hasVoice );
		var hasVoice = hasVoiceState[ 0 ];
		var setHasVoice = hasVoiceState[ 1 ];

		function apiPost( path, data ) {
			return wp.apiFetch( {
				url: cfg.restUrl + path,
				method: 'POST',
				headers: { 'X-WP-Nonce': cfg.nonce },
				data: data || {},
			} );
		}

		function onError( err ) {
			var msg = ( err && err.message ) ? err.message : __( 'Something went wrong.', 'voicewriter-ai' );
			setNotice( { status: 'error', text: msg } );
			setBusy( '' );
		}

		function train() {
			setNotice( null );
			setBusy( 'train' );
			apiPost( '/train', {} ).then( function ( res ) {
				setHasVoice( true );
				setBusy( '' );
				setNotice( {
					status: 'success',
					text: ( res && res.message ) ? res.message : __( 'Voice profile trained.', 'voicewriter-ai' ),
				} );
			} ).catch( onError );
		}

		function generate() {
			if ( ! topic.trim() ) {
				setNotice( { status: 'warning', text: __( 'Enter a topic first.', 'voicewriter-ai' ) } );
				return;
			}
			setNotice( null );
			setBusy( 'generate' );
			apiPost( '/generate', { topic: topic } ).then( function ( res ) {
				insertAsBlocks( res.content );
				setBusy( '' );
				setNotice( {
					status: 'success',
					text: __( 'Draft inserted into the editor.', 'voicewriter-ai' )
						+ ' ' + remainingText( res.remaining ),
				} );
			} ).catch( onError );
		}

		function repurpose() {
			var content = currentContent();
			if ( ! content ) {
				setNotice( { status: 'warning', text: __( 'Write or generate some content first.', 'voicewriter-ai' ) } );
				return;
			}
			setNotice( null );
			setBusy( 'repurpose' );
			apiPost( '/repurpose', { content: content, network: 'linkedin' } ).then( function ( res ) {
				insertAsBlocks( '\n\n— LinkedIn post —\n\n' + res.content );
				setBusy( '' );
				setNotice( {
					status: 'success',
					text: __( 'LinkedIn post added to the bottom of the editor.', 'voicewriter-ai' )
						+ ' ' + remainingText( res.remaining ),
				} );
			} ).catch( onError );
		}

		function remainingText( remaining ) {
			if ( typeof remaining !== 'number' ) {
				return '';
			}
			return wp.i18n.sprintf(
				/* translators: %d: free generations remaining this month. */
				__( '%d free generations left this month.', 'voicewriter-ai' ),
				remaining
			);
		}

		// No API key configured: prompt the user to add one.
		if ( ! cfg.hasApiKey ) {
			return el(
				PanelBody,
				{ title: __( 'VoiceWriter AI', 'voicewriter-ai' ), initialOpen: true },
				el( 'p', null, __( 'Add your AI provider API key (Claude, ChatGPT, or Gemini) to get started.', 'voicewriter-ai' ) ),
				el(
					Button,
					{ variant: 'primary', href: cfg.settingsUrl },
					__( 'Open settings', 'voicewriter-ai' )
				)
			);
		}

		var children = [];

		if ( notice ) {
			children.push(
				el(
					Notice,
					{
						status: notice.status,
						isDismissible: true,
						onRemove: function () {
							setNotice( null );
						},
						key: 'notice',
					},
					notice.text
				)
			);
		}

		// Step 1: train the voice.
		children.push(
			el(
				PanelBody,
				{ title: __( '1. Brand voice', 'voicewriter-ai' ), initialOpen: ! hasVoice, key: 'train' },
				el(
					'p',
					null,
					hasVoice
						? __( 'Your site voice is trained. Retrain anytime after publishing new posts.', 'voicewriter-ai' )
						: __( 'Learn your site\'s writing voice from your published posts.', 'voicewriter-ai' )
				),
				el(
					Button,
					{ variant: 'secondary', onClick: train, disabled: !! busy },
					busy === 'train' ? el( Spinner, null ) : ( hasVoice ? __( 'Retrain voice', 'voicewriter-ai' ) : __( 'Train voice', 'voicewriter-ai' ) )
				)
			)
		);

		// Step 2: generate a draft.
		children.push(
			el(
				PanelBody,
				{ title: __( '2. Generate a draft', 'voicewriter-ai' ), initialOpen: true, key: 'generate' },
				el( TextareaControl, {
					label: __( 'Topic or instruction', 'voicewriter-ai' ),
					placeholder: __( 'e.g. A beginner\'s guide to composting at home', 'voicewriter-ai' ),
					value: topic,
					onChange: setTopic,
					rows: 3,
				} ),
				el(
					Button,
					{ variant: 'primary', onClick: generate, disabled: !! busy },
					busy === 'generate' ? el( Spinner, null ) : __( 'Generate on-brand draft', 'voicewriter-ai' )
				)
			)
		);

		// Step 3: repurpose.
		children.push(
			el(
				PanelBody,
				{ title: __( '3. Repurpose', 'voicewriter-ai' ), initialOpen: false, key: 'repurpose' },
				el( 'p', null, __( 'Turn the current draft into a LinkedIn post. Pro unlocks X, newsletter, and more.', 'voicewriter-ai' ) ),
				el(
					Button,
					{ variant: 'secondary', onClick: repurpose, disabled: !! busy },
					busy === 'repurpose' ? el( Spinner, null ) : __( 'Create LinkedIn post', 'voicewriter-ai' )
				)
			)
		);

		return el( Fragment, null, children );
	}

	var icon = el(
		'svg',
		{ width: 24, height: 24, viewBox: '0 0 24 24', 'aria-hidden': true, focusable: false },
		el( 'path', {
			fill: 'currentColor',
			d: 'M12 3a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3zm-5 8a1 1 0 0 1 2 0 3 3 0 0 0 6 0 1 1 0 1 1 2 0 5 5 0 0 1-4 4.9V20h2a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2h2v-3.1A5 5 0 0 1 7 11z',
		} )
	);

	registerPlugin( 'voicewriter-ai', {
		render: function () {
			return el(
				Fragment,
				null,
				el(
					PluginSidebarMoreMenuItem,
					{ target: 'voicewriter-ai-sidebar', icon: icon },
					__( 'VoiceWriter AI', 'voicewriter-ai' )
				),
				el(
					PluginSidebar,
					{ name: 'voicewriter-ai-sidebar', title: __( 'VoiceWriter AI', 'voicewriter-ai' ), icon: icon },
					el( Panel, null )
				)
			);
		},
		icon: icon,
	} );
} )( window.wp );
