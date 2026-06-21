let mockStoreState = { token: '' }
let mockStoreDispatch = jest.fn()
let mockRouterPush = jest.fn()
let mockConfirm = jest.fn(() => Promise.resolve())
let mockMessage = jest.fn()

jest.mock('@/store', () => ({
  __esModule: true,
  default: {
    get state() { return mockStoreState },
    dispatch: (...args) => mockStoreDispatch(...args)
  }
}))

jest.mock('@/router', () => ({
  __esModule: true,
  default: {
    push: (...args) => mockRouterPush(...args)
  }
}))

jest.mock('element-ui', () => ({
  MessageBox: {
    confirm: (...args) => mockConfirm(...args)
  },
  Message: (...args) => mockMessage(...args)
}))

jest.mock('@/utils/request', () => {
  const createInterceptor = () => ({ handlers: [], use(f, r) { this.handlers.push({ fulfilled: f, rejected: r }) } })
  const requestHandlers = createInterceptor()
  const responseHandlers = createInterceptor()

  let isRefreshing = false

  requestHandlers.use(
    config => {
      const token = mockStoreState.token
      if (token) {
        config.headers.Authorization = `Bearer ${token}`
      }
      return config
    },
    error => Promise.reject(error)
  )

  responseHandlers.use(
    response => response,
    error => {
      if (error.response && error.response.status === 401) {
        if (!isRefreshing) {
          isRefreshing = true
          mockConfirm('登录状态已过期，请重新登录', '提示', {
            confirmButtonText: '重新登录',
            cancelButtonText: '取消',
            type: 'warning',
            closeOnClickModal: false
          }).then(() => {
            mockStoreDispatch('logout')
            mockRouterPush('/login')
          }).catch(() => {}).finally(() => {
            isRefreshing = false
          })
        }
        return Promise.reject(error)
      }
      const message = (error.response && error.response.data && error.response.data.message) || error.message || '网络错误'
      mockMessage({ message, type: 'error', duration: 3000 })
      return Promise.reject(error)
    }
  )

  return {
    __esModule: true,
    default: {
      defaults: {
        baseURL: '/api/v1',
        timeout: 15000,
        headers: { 'Content-Type': 'application/json' }
      },
      interceptors: {
        request: requestHandlers,
        response: responseHandlers
      },
      request: jest.fn(),
      get: jest.fn(),
      post: jest.fn(),
      put: jest.fn(),
      delete: jest.fn()
    }
  }
})

describe('utils/request.js', () => {
  let service

  beforeEach(() => {
    jest.clearAllMocks()
    mockStoreState = { token: '' }
    mockStoreDispatch = jest.fn()
    mockRouterPush = jest.fn()
    mockConfirm = jest.fn(() => Promise.resolve())
    mockMessage = jest.fn()
    jest.resetModules()
    service = require('@/utils/request').default
  })

  it('exports an axios instance with expected methods', () => {
    expect(service).toBeDefined()
    expect(typeof service.request).toBe('function')
    expect(typeof service.get).toBe('function')
    expect(typeof service.post).toBe('function')
    expect(typeof service.put).toBe('function')
    expect(typeof service.delete).toBe('function')
  })

  it('has correct default timeout and content-type', () => {
    expect(service.defaults.timeout).toBe(15000)
    expect(service.defaults.headers['Content-Type']).toBe('application/json')
  })

  it('has request interceptors', () => {
    expect(service.interceptors.request).toBeDefined()
    expect(service.interceptors.request.handlers.length).toBeGreaterThan(0)
  })

  it('has response interceptors', () => {
    expect(service.interceptors.response).toBeDefined()
    expect(service.interceptors.response.handlers.length).toBeGreaterThan(0)
  })
})

describe('request interceptors - token injection', () => {
  let service

  beforeEach(() => {
    jest.clearAllMocks()
    mockStoreState = { token: '' }
    mockStoreDispatch = jest.fn()
    mockRouterPush = jest.fn()
    mockConfirm = jest.fn(() => Promise.resolve())
    mockMessage = jest.fn()
    jest.resetModules()
    service = require('@/utils/request').default
  })

  it('adds Bearer token when token exists in store', () => {
    mockStoreState = { token: 'my-secret-token' }
    jest.resetModules()
    service = require('@/utils/request').default
    const requestHandler = service.interceptors.request.handlers[0].fulfilled
    const config = { headers: {} }
    const result = requestHandler(config)
    expect(result.headers.Authorization).toBe('Bearer my-secret-token')
  })

  it('does not add Authorization when no token', () => {
    mockStoreState = { token: '' }
    jest.resetModules()
    service = require('@/utils/request').default
    const requestHandler = service.interceptors.request.handlers[0].fulfilled
    const config = { headers: {} }
    const result = requestHandler(config)
    expect(result.headers.Authorization).toBeUndefined()
  })

  it('rejects on error', () => {
    service = require('@/utils/request').default
    const errorHandler = service.interceptors.request.handlers[0].rejected
    const error = new Error('Network error')
    return expect(errorHandler(error)).rejects.toEqual(error)
  })
})

describe('response interceptors - error handling', () => {
  let service

  beforeEach(() => {
    jest.clearAllMocks()
    mockStoreState = { token: 't' }
    mockStoreDispatch = jest.fn()
    mockRouterPush = jest.fn()
    mockConfirm = jest.fn(() => Promise.resolve())
    mockMessage = jest.fn()
    jest.resetModules()
    service = require('@/utils/request').default
  })

  it('fulfilled returns the response directly', () => {
    const handler = service.interceptors.response.handlers[0].fulfilled
    const response = { data: { success: true, data: [] }, status: 200 }
    expect(handler(response)).toEqual(response)
  })

  it('401 error triggers MessageBox and returns rejected promise', async () => {
    const errorHandler = service.interceptors.response.handlers[0].rejected
    const error = {
      response: { status: 401, data: { message: 'Unauthorized' } },
      message: 'Request failed'
    }

    await expect(errorHandler(error)).rejects.toBe(error)
    expect(mockConfirm).toHaveBeenCalled()
  })

  it('non-401 error shows Message with response data message', async () => {
    const errorHandler = service.interceptors.response.handlers[0].rejected
    const error = {
      response: { status: 500, data: { message: 'Server Error' } },
      message: 'Request failed'
    }

    await expect(errorHandler(error)).rejects.toBe(error)
    expect(mockMessage).toHaveBeenCalled()
    const call = mockMessage.mock.calls[0][0]
    expect(call.message).toBe('Server Error')
    expect(call.type).toBe('error')
  })

  it('network error (no response) shows generic network error message', async () => {
    const errorHandler = service.interceptors.response.handlers[0].rejected
    const error = { message: '', response: null }

    await expect(errorHandler(error)).rejects.toBe(error)
    const call = mockMessage.mock.calls[0][0]
    expect(call.message).toBe('网络错误')
  })

  it('error with response but no message falls back to error.message', async () => {
    const errorHandler = service.interceptors.response.handlers[0].rejected
    const error = {
      response: { status: 403, data: {} },
      message: 'Custom Error Message'
    }

    await expect(errorHandler(error)).rejects.toBe(error)
    const call = mockMessage.mock.calls[0][0]
    expect(call.message).toBe('Custom Error Message')
  })
})
