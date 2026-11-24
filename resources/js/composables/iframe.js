import axios from 'axios'
import { nextTick, ref, watch } from 'vue'

export function useIframe() {
  const open = ref(false)
  const iframeId = ref(null)

  const handleEvent = ({ data }) => {
    if (data.action == 'open') {
      nextTick(() => {
        setTimeout(() => open.value = true, 350)
      })
    }

    if (data.action == 'close') {
      nextTick(() => {
        open.value = false
      })
    }
  }

  const listen = () => {
    window.addEventListener('message', event => {
      handleEvent(event)
    })
  }

  const registerIframe = data => {
    iframeId.value = data.iframe
    window.parent.postMessage({
      action: 'registered',
      data
    }, '*'); 
  }

  watch(open, value => {
    if (! value) {
      setTimeout(() => {
        window.parent.postMessage({
          action: 'hide-iframe',
          iframe: iframeId.value
        }, '*')
      }, 500)
      
    }
  })

  return {
    open,
    listen,
    registerIframe,
  }
}