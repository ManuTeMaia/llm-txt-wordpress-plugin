( function () {
	const form = document.getElementById( 'llms-txt-settings' );
	if ( ! form ) {
		return;
	}

	const list = document.getElementById( 'llms-txt-sections' );
	const template = document.getElementById( 'llms-txt-section-template' );
	const prefixes = document.getElementById( 'llms-txt-prefixes' );
	const prefixTemplate = document.getElementById( 'llms-txt-prefix-template' );

	function reindexPrefixes() {
		if ( ! prefixes ) {
			return;
		}
		Array.prototype.forEach.call( prefixes.children, function ( row, index ) {
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /(prefixes\[)[^\]]+(\])/, '$1' + index + '$2' );
			} );
			const radio = row.querySelector( 'input[type="radio"]' );
			if ( radio ) {
				radio.value = String( index );
			}
		} );
	}

	function bindCard( card ) {
		const type = card.querySelector( '.llms-txt-type' );
		const sync = function () {
			if ( type ) {
				card.dataset.sectionType = type.value;
			}
		};
		if ( type ) {
			type.addEventListener( 'change', sync );
			sync();
		}
		const remove = card.querySelector( '.llms-txt-remove' );
		if ( remove ) {
			remove.addEventListener( 'click', function () {
				card.remove();
			} );
		}
		const up = card.querySelector( '.llms-txt-up' );
		if ( up ) {
			up.addEventListener( 'click', function () {
				const previous = card.previousElementSibling;
				if ( previous ) {
					list.insertBefore( card, previous );
				}
			} );
		}
		const down = card.querySelector( '.llms-txt-down' );
		if ( down ) {
			down.addEventListener( 'click', function () {
				const next = card.nextElementSibling;
				if ( next ) {
					list.insertBefore( next, card );
				}
			} );
		}
	}

	if ( list ) {
		list.querySelectorAll( '.llms-txt-card' ).forEach( bindCard );
	}

	const add = document.getElementById( 'llms-txt-add' );
	if ( add && template && list ) {
		add.addEventListener( 'click', function () {
			const index = list.querySelectorAll( '.llms-txt-card' ).length;
			const html = template.innerHTML.replaceAll( '__INDEX__', String( index ) );
			const wrap = document.createElement( 'div' );
			wrap.innerHTML = html.trim();
			const card = wrap.firstElementChild;
			if ( ! card ) {
				return;
			}
			list.appendChild( card );
			bindCard( card );
		} );
	}

	function bindPrefix( row ) {
		const remove = row.querySelector( '.llms-txt-remove-prefix' );
		if ( ! remove ) {
			return;
		}
		remove.addEventListener( 'click', function () {
			row.remove();
			reindexPrefixes();
		} );
	}

	if ( prefixes ) {
		prefixes.querySelectorAll( '.llms-txt-prefix' ).forEach( bindPrefix );
	}

	const addPrefix = document.getElementById( 'llms-txt-add-prefix' );
	if ( addPrefix && prefixTemplate && prefixes ) {
		addPrefix.addEventListener( 'click', function () {
			const index = prefixes.querySelectorAll( '.llms-txt-prefix' ).length;
			const html = prefixTemplate.innerHTML.replaceAll( '__INDEX__', String( index ) );
			const wrap = document.createElement( 'div' );
			wrap.innerHTML = html.trim();
			const row = wrap.firstElementChild;
			if ( ! row ) {
				return;
			}
			prefixes.appendChild( row );
			bindPrefix( row );
		} );
	}

	form.addEventListener( 'submit', function () {
		reindexPrefixes();
		if ( list ) {
			const cards = list.querySelectorAll( '.llms-txt-card' );
			cards.forEach( function ( card, index ) {
				card.querySelectorAll( '[name]' ).forEach( function ( field ) {
					field.name = field.name.replace( /(sections\[)[^\]]+(\])/, '$1' + index + '$2' );
				} );
			} );
		}
	} );

	document.querySelectorAll( '[data-llms-copy]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			const target = document.querySelector( button.getAttribute( 'data-llms-copy' ) );
			if ( ! target ) {
				return;
			}
			const text = target.value || target.textContent || '';
			const original = button.textContent;
			const done = function () {
				button.textContent = ( window.llmsTxtAdmin && window.llmsTxtAdmin.copied ) || 'Copied';
				window.setTimeout( function () {
					button.textContent = original;
				}, 1600 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done ).catch( function () {
					target.focus();
					target.select();
				} );
				return;
			}
			target.focus();
			target.select();
		} );
	} );
}() );
