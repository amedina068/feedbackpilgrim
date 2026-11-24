"use strict"

// The server URL comes from the src of this script tag, so one build works on
// every host. document.currentScript is null in a module script, so look for
// the tag by its file name then.
const pilgrimScript = document.currentScript || document.querySelector('script[src$="/pilgrim.js"][data-client]')
const serverUrl = new URL(pilgrimScript.src, window.location.href).origin
const dataClient = pilgrimScript.getAttribute('data-client')

window.pilgrimModal = iframeId => {
  let iframe = document.getElementById(iframeId)
  iframe.style.display = 'block'
  let message = { action: 'open' }
  iframe.contentWindow.postMessage(message, '*');
}

const pilgrim = payload => {
  if (! dataClient) {
    throw Error('No data client provided')
  }
  
  const headers = new Headers();
  headers.append('Authorization', `Bearer ${dataClient}`)
  headers.append('Content-Type', 'application/json')
  headers.append('Accept', 'application/json')

  const contact = payload.contact ? payload.contact : payload
  const releaseNotes = payload.releaseNotes ? payload.releaseNotes : {}
  const testimonials = payload.testimonials ? payload.testimonials : false
  const userHash = payload.userHash ? payload.userHash : null

  fetch(`${serverUrl}/api/v1/init`, {
    headers,
    method: 'POST',
    body: JSON.stringify({
      contact,
      releaseNotes,
      testimonials,
      user_hash: userHash
    })
  }).then(response => response.json())
    .then(data => {
      document.querySelectorAll('[data-pilgrim-link]').forEach(el => {
        el.addEventListener('click', e => {
          e.preventDefault();
          window.location.href = data.auth_url
        })
      });
      if (! data.releaseNotes) {
        return
      }
      if (data.releaseNotes.show && data.releaseNotes.url) {
          var iframe = document.createElement('iframe');
          iframe.setAttribute('src', data.releaseNotes.url);
          iframe.setAttribute('id', 'pilgrim-release-notes-iframe');
          iframe.style.width = '100%';
          iframe.style.zIndex = 2147483647;
          iframe.style.height = '100%';
          iframe.style.position = 'fixed';
          iframe.style.top = '0';
          iframe.style.left = '0';
          iframe.style.right = '0';
          iframe.style.bottom = '0';
          iframe.style.display = 'none';
          document.body.appendChild(iframe);
          document.querySelectorAll('[data-pilgrim-release-notes]').forEach(el => {
            el.addEventListener('click', e => {
              e.preventDefault();
              window.pilgrimModal('pilgrim-release-notes-iframe')
            })
          });
        window.addEventListener('message', ({ data }) => {
          if (data.action == 'hide-iframe') {
            let iframe = document.getElementById('pilgrim-release-notes-iframe')
            iframe.style.display = 'none'
          }
        });
        if (data.releaseNotes.open) {
          setTimeout(() => {
            window.pilgrimModal('pilgrim-release-notes-iframe')  
          }, 2000)
          
        }
      }

      if (data.testimonials && data.testimonials.url) {
        var testimonialsIframe = document.createElement('iframe');
        testimonialsIframe.setAttribute('src', data.testimonials.url);
        testimonialsIframe.setAttribute('id', 'pilgrim-testimonials-iframe');
        testimonialsIframe.style.width = '100%';
        testimonialsIframe.style.zIndex = 2147483647;
        testimonialsIframe.style.height = '100%';
        testimonialsIframe.style.position = 'fixed';
        testimonialsIframe.style.top = '0';
        testimonialsIframe.style.left = '0';
        testimonialsIframe.style.right = '0';
        testimonialsIframe.style.bottom = '0';
        testimonialsIframe.style.display = 'none';
        document.body.appendChild(testimonialsIframe);
        
        document.querySelectorAll('[data-pilgrim-testimonials]').forEach(el => {
          el.addEventListener('click', e => {
            e.preventDefault();
            window.pilgrimModal('pilgrim-testimonials-iframe')
          })
        });

        window.addEventListener('message', ({ data }) => {
          if (data.action == 'hide-iframe' && data.iframe == 'testimonials') {
            let iframe = document.getElementById('pilgrim-testimonials-iframe')
            iframe.style.display = 'none'
          }
        });
      }
    })
}

window.pilgrim = pilgrim