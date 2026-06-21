const mockRequest = jest.fn(() => Promise.resolve({ data: {} }))
jest.mock('@/utils/request', () => ({
  __esModule: true,
  default: mockRequest
}))

const request = require('@/utils/request').default

const api = require('@/api/dropship')

describe('api/dropship.js - API methods', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  it('exports all expected API functions', () => {
    expect(typeof api.getDropshipOrders).toBe('function')
    expect(typeof api.getDropshipOrder).toBe('function')
    expect(typeof api.createDropshipOrder).toBe('function')
    expect(typeof api.updateDropshipOrder).toBe('function')
    expect(typeof api.deleteDropshipOrder).toBe('function')
    expect(typeof api.getDropshipStatistics).toBe('function')
    expect(typeof api.getDropshipStatusOptions).toBe('function')
    expect(typeof api.getDropshipChannelOptions).toBe('function')
    expect(typeof api.reviewDropshipOrder).toBe('function')
    expect(typeof api.batchReviewDropshipOrders).toBe('function')
    expect(typeof api.pushDropshipOrder).toBe('function')
    expect(typeof api.batchPushDropshipOrders).toBe('function')
    expect(typeof api.retryPushDropshipOrder).toBe('function')
    expect(typeof api.updateDropshipOrderStatus).toBe('function')
    expect(typeof api.cancelDropshipOrder).toBe('function')
    expect(typeof api.syncDropshipTracking).toBe('function')
  })

  describe('getDropshipOrders', () => {
    it('calls GET /dropship/orders with params', async () => {
      const params = { page: 1, per_page: 20, status: 'pending_review' }
      await api.getDropshipOrders(params)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders',
        method: 'get',
        params
      })
    })

    it('works without params', async () => {
      await api.getDropshipOrders()
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders',
        method: 'get',
        params: undefined
      })
    })
  })

  describe('getDropshipOrder', () => {
    it('calls GET /dropship/orders/:id', async () => {
      await api.getDropshipOrder(123)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/123',
        method: 'get'
      })
    })
  })

  describe('createDropshipOrder', () => {
    it('calls POST /dropship/orders with data', async () => {
      const data = { receiver_name: 'John', items: [{ sku: 'A', quantity: 1 }] }
      await api.createDropshipOrder(data)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders',
        method: 'post',
        data
      })
    })
  })

  describe('updateDropshipOrder', () => {
    it('calls PUT /dropship/orders/:id with data', async () => {
      const data = { receiver_phone: '999' }
      await api.updateDropshipOrder(42, data)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/42',
        method: 'put',
        data
      })
    })
  })

  describe('deleteDropshipOrder', () => {
    it('calls DELETE /dropship/orders/:id', async () => {
      await api.deleteDropshipOrder(77)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/77',
        method: 'delete'
      })
    })
  })

  describe('getDropshipStatistics', () => {
    it('calls GET /dropship/statistics', async () => {
      const params = { date_range: ['2026-01-01', '2026-06-21'] }
      await api.getDropshipStatistics(params)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/statistics',
        method: 'get',
        params
      })
    })
  })

  describe('getDropshipStatusOptions', () => {
    it('calls GET /dropship/status-options', async () => {
      await api.getDropshipStatusOptions()
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/status-options',
        method: 'get'
      })
    })
  })

  describe('getDropshipChannelOptions', () => {
    it('calls GET /dropship/channel-options', async () => {
      await api.getDropshipChannelOptions()
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/channel-options',
        method: 'get'
      })
    })
  })

  describe('reviewDropshipOrder', () => {
    it('calls POST /dropship/orders/:id/review with data', async () => {
      const data = { pass: true, remark: 'OK' }
      await api.reviewDropshipOrder(10, data)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/10/review',
        method: 'post',
        data
      })
    })
  })

  describe('batchReviewDropshipOrders', () => {
    it('calls POST /dropship/batch-review with data', async () => {
      const data = { ids: [1, 2, 3], pass: true }
      await api.batchReviewDropshipOrders(data)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/batch-review',
        method: 'post',
        data
      })
    })
  })

  describe('pushDropshipOrder', () => {
    it('calls POST /dropship/orders/:id/push', async () => {
      await api.pushDropshipOrder(5)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/5/push',
        method: 'post'
      })
    })
  })

  describe('batchPushDropshipOrders', () => {
    it('calls POST /dropship/batch-push with ids', async () => {
      const data = { ids: [1, 2] }
      await api.batchPushDropshipOrders(data)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/batch-push',
        method: 'post',
        data
      })
    })
  })

  describe('retryPushDropshipOrder', () => {
    it('calls POST /dropship/orders/:id/retry-push', async () => {
      await api.retryPushDropshipOrder(9)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/9/retry-push',
        method: 'post'
      })
    })
  })

  describe('updateDropshipOrderStatus', () => {
    it('calls POST /dropship/orders/:id/update-status with data', async () => {
      const data = { status: 'shipped' }
      await api.updateDropshipOrderStatus(11, data)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/11/update-status',
        method: 'post',
        data
      })
    })
  })

  describe('cancelDropshipOrder', () => {
    it('calls POST /dropship/orders/:id/cancel with reason', async () => {
      const data = { reason: 'Customer cancelled' }
      await api.cancelDropshipOrder(15, data)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/15/cancel',
        method: 'post',
        data
      })
    })
  })

  describe('syncDropshipTracking', () => {
    it('calls POST /dropship/orders/:id/sync-tracking', async () => {
      await api.syncDropshipTracking(20)
      expect(request).toHaveBeenCalledWith({
        url: '/dropship/orders/20/sync-tracking',
        method: 'post'
      })
    })
  })
})
