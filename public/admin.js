(() => {
  document.addEventListener('DOMContentLoaded', () => {
    // Fields marked data-custom-api are the ones only Custom mode uses (the
    // Challenge URL); data-selfhosted-api marks the inverse — settings the
    // backend owns in Custom mode, so the control goes dead there (Complexity,
    // Expiration). Both attributes are rendered server-side, so the state is
    // already right on load; this only keeps it right while the API Mode
    // dropdown is being changed.
    function onApiChange(api) {
      const custom = api === 'custom';
      [...document.querySelectorAll('[data-custom-api]')].forEach((el) => {
        el.disabled = !custom;
      });
      [...document.querySelectorAll('[data-selfhosted-api]')].forEach((el) => {
        el.disabled = custom;
      });
      [...document.querySelectorAll('[data-selfhosted-note]')].forEach((el) => {
        el.style.display = custom ? '' : 'none';
      });
    }
    const apiEl = document.querySelector('#openporte_api');
    if (apiEl) {
      apiEl.addEventListener('change', (ev) => onApiChange(ev.target.value));
      onApiChange(apiEl.value);
    }

    // Preset dropdowns with a "Custom" choice reveal a companion number input.
    // Shared by Expiration and Replay limit; both render the same markup.
    function wireCustomToggle(selectId, inputId) {
      const selectEl = document.querySelector(selectId);
      const inputEl = document.querySelector(inputId);
      if (!selectEl || !inputEl) {
        return;
      }
      const sync = (value) => {
        inputEl.style.display = value === 'custom' ? '' : 'none';
      };
      selectEl.addEventListener('change', (ev) => sync(ev.target.value));
      sync(selectEl.value);
    }
    wireCustomToggle('#openporte_expires', '#openporte_expires_custom');
    wireCustomToggle('#openporte_replaylimit', '#openporte_replaylimit_custom');

    /**
     * Selects a pre-rendered icon without removing the button's SVG children.
     *
     * @since 1.30.0
     * @param {HTMLButtonElement} button Action button.
     * @param {string} icon Icon name.
     * @return {void}
     */
    function setPasswordIcon( button, icon ) {
      button.querySelectorAll( '[data-icon]' ).forEach( ( element ) => {
        element.hidden = element.dataset.icon !== icon;
      } );
    }

    // Labels are rendered server-side so icon-only controls stay translated.
    document.querySelectorAll( '.openporte-toggle-password' ).forEach( ( button ) => {
      button.addEventListener( 'click', () => {
        const input = document.getElementById( button.dataset.target );
        if ( ! input || input.disabled ) {
          return;
        }
        const reveal = input.type === 'password';
        const label = reveal ? button.dataset.labelHide : button.dataset.labelShow;
        input.type = reveal ? 'text' : 'password';
        button.setAttribute( 'aria-label', label );
        button.title = label;
        if ( button.querySelector( '[data-icon]' ) ) {
          setPasswordIcon( button, reveal ? 'unseen' : 'seen' );
        } else {
          // The deprecated generic renderer still exposes ordinary text buttons.
          button.textContent = label;
        }
      } );
    } );

    // Keep the HTTP fallback: the Clipboard API requires a secure context.
    document.querySelectorAll( '.openporte-copy-password' ).forEach( ( button ) => {
      const status = document.getElementById( button.dataset.status );
      let resetTimer = null;
      let announcementTimer = null;
      let copying = false;

      /**
       * Shows and announces the outcome, including consecutive identical results.
       *
       * @since 1.30.0
       * @param {boolean} copied Whether copying succeeded.
       * @return {void}
       */
      const flash = ( copied ) => {
        const label = copied ? button.dataset.labelCopied : button.dataset.labelFailed;
        window.clearTimeout( resetTimer );
        window.clearTimeout( announcementTimer );
        setPasswordIcon( button, copied ? 'check' : 'copy' );
        button.title = label;
        // The accessible name remains the action; the live region owns feedback.
        if ( status ) {
          status.textContent = '';
          // Separate mutations allow assistive technology to announce repeats.
          announcementTimer = window.setTimeout( () => {
            status.textContent = label;
          }, 100 );
        }
        resetTimer = window.setTimeout( () => {
          setPasswordIcon( button, 'copy' );
          button.title = button.dataset.labelCopy;
          if ( copied && status ) {
            status.textContent = '';
          }
        }, 2000 );
      };

      button.addEventListener( 'click', async () => {
        const input = document.getElementById( button.dataset.target );
        if ( ! input || input.disabled || copying ) {
          return;
        }
        // Ignore overlapping requests so a late result cannot overwrite a newer one.
        copying = true;
        if ( navigator.clipboard && window.isSecureContext ) {
          try {
            await navigator.clipboard.writeText( input.value );
            flash( true );
          } catch ( error ) {
            flash( false );
          } finally {
            copying = false;
          }
          return;
        }
        const activeElement = document.activeElement;
        const helper = document.createElement( 'textarea' );
        helper.value = input.value;
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild( helper );
        helper.select();
        let copied = false;
        try {
          copied = document.execCommand( 'copy' );
        } catch ( error ) {
          copied = false;
        } finally {
          helper.remove();
          // Selecting the helper steals keyboard focus; return it to the caller.
          if ( activeElement ) {
            activeElement.focus( { preventScroll: true } );
          }
          copying = false;
        }
        flash( copied );
      } );
    } );

    // Fills the field only; Save Changes still controls persistence.
    document.querySelectorAll( '.openporte-regenerate-secret' ).forEach( ( button ) => {
      button.addEventListener( 'click', () => {
        const input = document.getElementById( button.dataset.target );
        if ( ! input || input.disabled || ! window.crypto || ! window.crypto.getRandomValues ) {
          return;
        }
        const bytes = new Uint8Array( 32 );
        window.crypto.getRandomValues( bytes );
        input.value = [ ...bytes ]
          .map( ( byte ) => byte.toString( 16 ).padStart( 2, '0' ) )
          .join( '' );
      } );
    } );
  });
})();
