import Vue from 'vue'
import ElementUI from 'element-ui'

Vue.use(ElementUI)
Vue.config.productionTip = false

if (!global.import) {
  global.import = {}
}
if (!global.import.meta) {
  global.import.meta = {}
}
if (!global.import.meta.env) {
  global.import.meta.env = {}
}

Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation((query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(),
    removeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn()
  }))
})

document.execCommand = jest.fn()

global.console.warn = jest.fn()
global.console.error = jest.fn()
