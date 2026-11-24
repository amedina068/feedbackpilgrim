import './bootstrap'
import hljs from 'highlight.js'
import javascript from 'highlight.js/lib/languages/javascript'
import xml from 'highlight.js/lib/languages/xml'
import 'highlight.js/styles/nord.css'

import Quill from 'quill'
import "quill/dist/quill.core.css";
hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('xml', xml)

window.hljs = hljs
window.Quill = Quill

import { createApp } from 'vue'
import { serverTemplateDelimiters } from './server-template'

import BaseDropdown from './components/BaseDropdown.vue'
import ClientSelector from './components/ClientSelector.vue'
import CopyButton from './components/CopyButton.vue'
import McpSetup from './components/McpSetup.vue'
import ContactFormCustomFields from './components/ContactFormCustomFields.vue'
import ContactFormDynamicRow from './components/ContactFormDynamicRow.vue'
import StagesIndex from './components/StagesIndex.vue'
import ReleaseNotesModal from './components/ReleaseNotesModal.vue'
import TestimonialsModal from './components/TestimonialsModal.vue'
import DashboardChart from './components/DashboardChart.vue'
import TopContactsList from './components/TopContactsList.vue'
import DashboardSummary from './components/DashboardSummary.vue'
import Modal from './components/Modal.vue'
import AutoHideFlash from './components/AutoHideFlash.vue'

import { MenuItem } from '@headlessui/vue'

const app = createApp({
    components: {
        BaseDropdown,
        ClientSelector,
        CopyButton,
        McpSetup,
        ContactFormCustomFields,
        ContactFormDynamicRow,
        MenuItem,
        ReleaseNotesModal,
        TestimonialsModal,
        DashboardChart,
        TopContactsList,
        DashboardSummary,
        Modal,
        AutoHideFlash,
    }
})

app.config.compilerOptions.delimiters = serverTemplateDelimiters
app.mount('#app')
