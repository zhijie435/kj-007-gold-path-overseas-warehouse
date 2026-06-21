jest.mock('@/api/dropship', () => ({
  getDropshipOrders: jest.fn(() => Promise.resolve({ data: { data: [], total: 0 } })),
  getDropshipStatistics: jest.fn(() => Promise.resolve({ data: {} })),
  getDropshipStatusOptions: jest.fn(() => Promise.resolve({ data: { success: true, data: [
    { value: 'draft', label: '草稿', color: 'info' },
    { value: 'pending_review', label: '待审核', color: 'warning' }
  ] } })),
  getDropshipChannelOptions: jest.fn(() => Promise.resolve({ data: { success: true, data: [] } })),
  reviewDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } })),
  batchReviewDropshipOrders: jest.fn(() => Promise.resolve({ data: { success: true } })),
  pushDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } })),
  batchPushDropshipOrders: jest.fn(() => Promise.resolve({ data: { success: true } })),
  retryPushDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } })),
  cancelDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } }))
}))

import Vue from 'vue'
import { mount, createLocalVue } from '@vue/test-utils'
import ElementUI from 'element-ui'
import ListPage from '@/views/OverseaDropship/List.vue'
import { createMockMessage } from '../../helpers'

Vue.use(ElementUI)

const mockMessage = () => {
  const fn = jest.fn()
  fn.success = jest.fn()
  fn.warning = jest.fn()
  fn.error = jest.fn()
  fn.info = jest.fn()
  return fn
}

describe('OverseaDropship/List.vue - Status permission methods', () => {
  let wrapper

  beforeEach(() => {
    const localVue = createLocalVue()
    localVue.use(ElementUI)
    const mockRouter = { push: jest.fn() }
    wrapper = mount(ListPage, {
      localVue,
      mocks: {
        $router: mockRouter,
        $message: mockMessage(),
        $confirm: jest.fn(() => Promise.resolve())
      }
    })
  })

  afterEach(() => {
    wrapper.destroy()
  })

  describe('canReview', () => {
    it('returns true for draft status', () => {
      expect(wrapper.vm.canReview('draft')).toBe(true)
    })

    it('returns true for pending_review status', () => {
      expect(wrapper.vm.canReview('pending_review')).toBe(true)
    })

    it('returns false for review_pass status', () => {
      expect(wrapper.vm.canReview('review_pass')).toBe(false)
    })

    it('returns false for already shipped status', () => {
      expect(wrapper.vm.canReview('shipped')).toBe(false)
    })

    it('returns false for cancelled terminal status', () => {
      expect(wrapper.vm.canReview('cancelled')).toBe(false)
    })

    it('returns false for completed terminal status', () => {
      expect(wrapper.vm.canReview('completed')).toBe(false)
    })
  })

  describe('canPush', () => {
    it('returns true for review_pass status', () => {
      expect(wrapper.vm.canPush('review_pass')).toBe(true)
    })

    it('returns true for auto_review_pass status', () => {
      expect(wrapper.vm.canPush('auto_review_pass')).toBe(true)
    })

    it('returns true for push_failed status (retry push)', () => {
      expect(wrapper.vm.canPush('push_failed')).toBe(true)
    })

    it('returns false for draft status (needs review first)', () => {
      expect(wrapper.vm.canPush('draft')).toBe(false)
    })

    it('returns false for already pushing status', () => {
      expect(wrapper.vm.canPush('pushing')).toBe(false)
    })

    it('returns false for push_success status', () => {
      expect(wrapper.vm.canPush('push_success')).toBe(false)
    })

    it('returns false for terminal status', () => {
      expect(wrapper.vm.canPush('completed')).toBe(false)
      expect(wrapper.vm.canPush('cancelled')).toBe(false)
    })
  })

  describe('canCancel', () => {
    it('returns true for draft status', () => {
      expect(wrapper.vm.canCancel('draft')).toBe(true)
    })

    it('returns true for pending_review status', () => {
      expect(wrapper.vm.canCancel('pending_review')).toBe(true)
    })

    it('returns true for auto_review_pass status', () => {
      expect(wrapper.vm.canCancel('auto_review_pass')).toBe(true)
    })

    it('returns true for review_pass status', () => {
      expect(wrapper.vm.canCancel('review_pass')).toBe(true)
    })

    it('returns true for push_failed status', () => {
      expect(wrapper.vm.canCancel('push_failed')).toBe(true)
    })

    it('returns true for processing status (can cancel during processing)', () => {
      expect(wrapper.vm.canCancel('processing')).toBe(true)
    })

    it('returns true for exception status', () => {
      expect(wrapper.vm.canCancel('exception')).toBe(true)
    })

    it('returns false for pushing status (cannot cancel during push)', () => {
      expect(wrapper.vm.canCancel('pushing')).toBe(false)
    })

    it('returns false for push_success status (after push accepted)', () => {
      expect(wrapper.vm.canCancel('push_success')).toBe(false)
    })

    it('returns false for picked/packed/shipped status', () => {
      expect(wrapper.vm.canCancel('picked')).toBe(false)
      expect(wrapper.vm.canCancel('packed')).toBe(false)
      expect(wrapper.vm.canCancel('shipped')).toBe(false)
    })

    it('returns false for terminal statuses', () => {
      expect(wrapper.vm.canCancel('completed')).toBe(false)
      expect(wrapper.vm.canCancel('cancelled')).toBe(false)
      expect(wrapper.vm.canCancel('returned')).toBe(false)
      expect(wrapper.vm.canCancel('review_reject')).toBe(false)
    })
  })

  describe('canRetry', () => {
    it('returns true for push_failed status', () => {
      expect(wrapper.vm.canRetry('push_failed')).toBe(true)
    })

    it('returns true for exception status', () => {
      expect(wrapper.vm.canRetry('exception')).toBe(true)
    })

    it('returns false for normal processing statuses', () => {
      expect(wrapper.vm.canRetry('draft')).toBe(false)
      expect(wrapper.vm.canRetry('pending_review')).toBe(false)
      expect(wrapper.vm.canRetry('review_pass')).toBe(false)
      expect(wrapper.vm.canRetry('pushing')).toBe(false)
      expect(wrapper.vm.canRetry('push_success')).toBe(false)
      expect(wrapper.vm.canRetry('shipped')).toBe(false)
    })

    it('returns false for terminal statuses', () => {
      expect(wrapper.vm.canRetry('completed')).toBe(false)
      expect(wrapper.vm.canRetry('cancelled')).toBe(false)
    })
  })

  describe('getStatusColor', () => {
    it('returns correct color for each status', () => {
      expect(wrapper.vm.getStatusColor('draft')).toBe('info')
      expect(wrapper.vm.getStatusColor('pending_review')).toBe('warning')
      expect(wrapper.vm.getStatusColor('review_pass')).toBe('success')
      expect(wrapper.vm.getStatusColor('review_reject')).toBe('danger')
      expect(wrapper.vm.getStatusColor('pushing')).toBe('primary')
      expect(wrapper.vm.getStatusColor('push_failed')).toBe('danger')
      expect(wrapper.vm.getStatusColor('shipped')).toBe('success')
      expect(wrapper.vm.getStatusColor('in_transit')).toBe('warning')
      expect(wrapper.vm.getStatusColor('completed')).toBe('success')
      expect(wrapper.vm.getStatusColor('cancelled')).toBe('info')
      expect(wrapper.vm.getStatusColor('exception')).toBe('danger')
    })

    it('returns info as default for unknown status', () => {
      expect(wrapper.vm.getStatusColor('unknown_status_xyz')).toBe('info')
    })
  })

  describe('getStatusLabel', () => {
    it('returns status value when statusOptions is empty', () => {
      wrapper.setData({ statusOptions: [] })
      expect(wrapper.vm.getStatusLabel('draft')).toBe('draft')
    })
  })

  describe('initial data', () => {
    it('initializes with correct default statistics', () => {
      expect(wrapper.vm.stats.pendingReview).toBe(0)
      expect(wrapper.vm.stats.pendingPush).toBe(0)
      expect(wrapper.vm.stats.inTransit).toBe(0)
      expect(wrapper.vm.stats.exception).toBe(0)
      expect(wrapper.vm.stats.todayNew).toBe(0)
      expect(wrapper.vm.stats.completeRate).toBe(0)
    })

    it('initializes filter form with empty values', () => {
      expect(wrapper.vm.filterForm.keyword).toBe('')
      expect(wrapper.vm.filterForm.status).toEqual([])
      expect(wrapper.vm.filterForm.warehouseId).toBeNull()
      expect(wrapper.vm.filterForm.channel).toBeNull()
      expect(wrapper.vm.filterForm.country).toBeNull()
      expect(wrapper.vm.filterForm.dateRange).toEqual([])
    })

    it('initializes pagination correctly', () => {
      expect(wrapper.vm.pagination.currentPage).toBe(1)
      expect(wrapper.vm.pagination.pageSize).toBe(20)
    })
  })

  describe('handleReset', () => {
    it('resets filter form to defaults and resets pagination', async () => {
      wrapper.setData({
        filterForm: {
          keyword: 'test',
          status: ['draft'],
          warehouseId: 1,
          channel: 'shopify',
          country: 'US',
          dateRange: ['2026-01-01', '2026-06-21']
        }
      })
      const spy = jest.spyOn(wrapper.vm, 'fetchList')
      wrapper.vm.handleReset()
      expect(wrapper.vm.filterForm.keyword).toBe('')
      expect(wrapper.vm.filterForm.status).toEqual([])
      expect(wrapper.vm.filterForm.warehouseId).toBeNull()
      expect(wrapper.vm.filterForm.channel).toBeNull()
      expect(wrapper.vm.filterForm.country).toBeNull()
      expect(wrapper.vm.filterForm.dateRange).toEqual([])
      expect(wrapper.vm.pagination.currentPage).toBe(1)
      expect(spy).toHaveBeenCalled()
    })
  })

  describe('handleSelectionChange', () => {
    it('extracts ids from selected rows', () => {
      wrapper.vm.handleSelectionChange([
        { id: 1, dropshipNo: 'DS001' },
        { id: 5, dropshipNo: 'DS005' },
        { id: 10, dropshipNo: 'DS010' }
      ])
      expect(wrapper.vm.selectedIds).toEqual([1, 5, 10])
    })

    it('handles empty selection', () => {
      wrapper.vm.handleSelectionChange([])
      expect(wrapper.vm.selectedIds).toEqual([])
    })
  })

  describe('handleSizeChange and handleCurrentChange', () => {
    it('handleSizeChange updates pageSize and fetches list', () => {
      const spy = jest.spyOn(wrapper.vm, 'fetchList')
      wrapper.vm.handleSizeChange(50)
      expect(wrapper.vm.pagination.pageSize).toBe(50)
      expect(spy).toHaveBeenCalled()
    })

    it('handleCurrentChange updates currentPage and fetches list', () => {
      const spy = jest.spyOn(wrapper.vm, 'fetchList')
      wrapper.vm.handleCurrentChange(3)
      expect(wrapper.vm.pagination.currentPage).toBe(3)
      expect(spy).toHaveBeenCalled()
    })
  })

  describe('handleSearch', () => {
    it('resets to first page and fetches list', () => {
      wrapper.setData({ pagination: { currentPage: 5, pageSize: 20 } })
      const spy = jest.spyOn(wrapper.vm, 'fetchList')
      wrapper.vm.handleSearch()
      expect(wrapper.vm.pagination.currentPage).toBe(1)
      expect(spy).toHaveBeenCalled()
    })
  })

  describe('handleCreate', () => {
    it('navigates to create page', () => {
      wrapper.vm.handleCreate()
      expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ path: '/dropship/orders/create' })
    })
  })

  describe('submitReview - validation', () => {
    it('warns when rejecting without remark', async () => {
      wrapper.setData({
        reviewForm: { result: 'reject', remark: '   ' },
        reviewDialogVisible: true,
        currentReviewId: 1
      })
      await wrapper.vm.submitReview()
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith('请填写拒绝原因')
    })
  })
})

describe('OverseaDropship/List.vue - Golden Path: Full Interaction Flows', () => {
  let wrapper
  let mockRouter
  let mockMessage
  let api

  const flushPromises = () => new Promise(resolve => setTimeout(resolve, 10))

  const mountWithMocks = (extra = {}) => {
    const localVue = createLocalVue()
    localVue.use(ElementUI)
    mockRouter = { push: jest.fn() }
    mockMessage = {
      success: jest.fn(),
      warning: jest.fn(),
      error: jest.fn(),
      info: jest.fn()
    }
    jest.clearAllMocks()
    api = require('@/api/dropship')

    wrapper = mount(ListPage, {
      localVue,
      mocks: {
        $router: mockRouter,
        $message: mockMessage,
        $confirm: jest.fn(() => Promise.resolve()),
        $prompt: jest.fn(() => Promise.resolve({ value: 'test-reason' })),
        ...extra
      }
    })
  }

  afterEach(() => {
    if (wrapper) wrapper.destroy()
  })

  describe('fetchOptions - status and channel options loading', () => {
    it('fetches status and channel options on mount', async () => {
      mountWithMocks()
      await flushPromises()
      expect(api.getDropshipStatusOptions).toHaveBeenCalled()
      expect(api.getDropshipChannelOptions).toHaveBeenCalled()
    })

    it('maps status options from API response to statusOptions array', async () => {
      mountWithMocks()
      await flushPromises()
      expect(wrapper.vm.statusOptions.length).toBeGreaterThan(0)
      expect(wrapper.vm.statusOptions[0].value).toBe('draft')
      expect(wrapper.vm.statusOptions[0].label).toBe('草稿')
    })

    it('channelOptions populated from API response', async () => {
      mountWithMocks()
      await flushPromises()
      expect(Array.isArray(wrapper.vm.channelOptions)).toBe(true)
    })
  })

  describe('fetchList - list data loading with filters', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('sets loading=true while fetching, false after', async () => {
      const p = wrapper.vm.fetchList()
      expect(wrapper.vm.loading).toBe(true)
      await p
      await flushPromises()
      expect(wrapper.vm.loading).toBe(false)
    })

    it('includes pagination params in API call', async () => {
      wrapper.setData({ pagination: { currentPage: 2, pageSize: 50 } })
      await wrapper.vm.fetchList()
      await flushPromises()
      expect(api.getDropshipOrders).toHaveBeenCalledWith(
        expect.objectContaining({ page: 2, per_page: 50 })
      )
    })

    it('includes keyword filter when set', async () => {
      wrapper.setData({ filterForm: { keyword: 'DS001', status: [], warehouseId: null, channel: null, country: null, dateRange: [] } })
      await wrapper.vm.fetchList()
      await flushPromises()
      expect(api.getDropshipOrders).toHaveBeenCalledWith(
        expect.objectContaining({ keyword: 'DS001' })
      )
    })

    it('includes status filter (join by comma) when selected', async () => {
      wrapper.setData({ filterForm: { keyword: '', status: ['pending_review', 'review_pass'], warehouseId: null, channel: null, country: null, dateRange: [] } })
      await wrapper.vm.fetchList()
      await flushPromises()
      expect(api.getDropshipOrders).toHaveBeenCalledWith(
        expect.objectContaining({ status: 'pending_review,review_pass' })
      )
    })

    it('includes warehouse_id, source_channel, receiver_country filters', async () => {
      wrapper.setData({ filterForm: { keyword: '', status: [], warehouseId: 3, channel: 'shopify', country: 'JP', dateRange: [] } })
      await wrapper.vm.fetchList()
      await flushPromises()
      const callArgs = api.getDropshipOrders.mock.calls[0][0]
      expect(callArgs.warehouse_id).toBe(3)
      expect(callArgs.source_channel).toBe('shopify')
      expect(callArgs.receiver_country).toBe('JP')
    })

    it('includes date_range when both start and end present', async () => {
      wrapper.setData({ filterForm: { keyword: '', status: [], warehouseId: null, channel: null, country: null, dateRange: ['2026-01-01', '2026-06-21'] } })
      await wrapper.vm.fetchList()
      await flushPromises()
      const callArgs = api.getDropshipOrders.mock.calls[0][0]
      expect(callArgs.date_range).toEqual(['2026-01-01', '2026-06-21'])
    })

    it('maps response data to tableData and total', async () => {
      // default mock returns { data: [], total: 0 }
      expect(wrapper.vm.tableData).toEqual([])
      expect(wrapper.vm.total).toBe(0)
    })
  })

  describe('fetchStats - statistics dashboard loading', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('calls getDropshipStatistics API on mount', async () => {
      expect(api.getDropshipStatistics).toHaveBeenCalled()
    })

    it('maps stats fields correctly from snake_case to camelCase', async () => {
      // override mock for this test with a rich response
      jest.clearAllMocks()
      const oldImpl = api.getDropshipStatistics
      api.getDropshipStatistics = jest.fn(() => Promise.resolve({
        data: {
          success: true,
          data: {
            pending_review: 42,
            pending_push: 17,
            in_transit: 88,
            exceptions: 5,
            today: { orders: 12 },
            completion_rate: 87,
            warehouses: [
              { warehouse_id: 1, warehouse_name: 'US LA' },
              { warehouse_id: 5, warehouse_name: 'JP Tokyo' }
            ]
          }
        }
      }))
      wrapper = null
      mountWithMocks()
      await flushPromises()
      expect(wrapper.vm.stats.pendingReview).toBe(42)
      expect(wrapper.vm.stats.pendingPush).toBe(17)
      expect(wrapper.vm.stats.inTransit).toBe(88)
      expect(wrapper.vm.stats.exception).toBe(5)
      expect(wrapper.vm.stats.todayNew).toBe(12)
      expect(wrapper.vm.stats.completeRate).toBe(87)
      expect(wrapper.vm.warehouseOptions).toEqual([
        { id: 1, name: 'US LA' },
        { id: 5, name: 'JP Tokyo' }
      ])
      api.getDropshipStatistics = oldImpl
    })
  })

  describe('submitReview - golden flow: review dialog submit', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('submits pass review: calls API, shows success, closes dialog, refreshes', async () => {
      wrapper.setData({
        currentReviewId: 55,
        reviewForm: { result: 'pass', remark: '' },
        reviewDialogVisible: true
      })
      await wrapper.vm.submitReview()
      await flushPromises()
      expect(api.reviewDropshipOrder).toHaveBeenCalledWith(55, {
        pass: true,
        remark: ''
      })
      expect(mockMessage.success).toHaveBeenCalledWith('审核成功')
      expect(wrapper.vm.reviewDialogVisible).toBe(false)
      expect(wrapper.vm.reviewLoading).toBe(false)
    })

    it('submits reject review: validates non-empty remark first', async () => {
      wrapper.setData({
        currentReviewId: 55,
        reviewForm: { result: 'reject', remark: '   ' },
        reviewDialogVisible: true
      })
      await wrapper.vm.submitReview()
      expect(mockMessage.warning).toHaveBeenCalledWith('请填写拒绝原因')
      expect(api.reviewDropshipOrder).not.toHaveBeenCalled()
    })

    it('submits reject review with valid remark: calls API with pass=false', async () => {
      wrapper.setData({
        currentReviewId: 55,
        reviewForm: { result: 'reject', remark: 'Missing documentation' },
        reviewDialogVisible: true
      })
      await wrapper.vm.submitReview()
      await flushPromises()
      expect(api.reviewDropshipOrder).toHaveBeenCalledWith(55, {
        pass: false,
        remark: 'Missing documentation'
      })
      expect(mockMessage.success).toHaveBeenCalledWith('审核成功')
    })

    it('reviewLoading guards during async call', async () => {
      wrapper.setData({
        currentReviewId: 1,
        reviewForm: { result: 'pass', remark: '' },
        reviewDialogVisible: true
      })
      const p = wrapper.vm.submitReview()
      expect(wrapper.vm.reviewLoading).toBe(true)
      await p
      await flushPromises()
      expect(wrapper.vm.reviewLoading).toBe(false)
    })

    it('fetchList and fetchStats called after review success', async () => {
      wrapper.setData({
        currentReviewId: 1,
        reviewForm: { result: 'pass', remark: '' },
        reviewDialogVisible: true
      })
      const listSpy = jest.spyOn(wrapper.vm, 'fetchList')
      const statsSpy = jest.spyOn(wrapper.vm, 'fetchStats')
      await wrapper.vm.submitReview()
      await flushPromises()
      expect(listSpy).toHaveBeenCalled()
      expect(statsSpy).toHaveBeenCalled()
    })
  })

  describe('handleView/handleReview/handleCreate - navigation and dialog opens', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('handleCreate navigates to /dropship/orders/create', () => {
      wrapper.vm.handleCreate()
      expect(mockRouter.push).toHaveBeenCalledWith({ path: '/dropship/orders/create' })
    })

    it('handleView navigates to order detail page using row.id', () => {
      wrapper.vm.handleView({ id: 123, dropshipNo: 'DS000123' })
      expect(mockRouter.push).toHaveBeenCalledWith({ path: '/dropship/orders/123' })
    })

    it('handleView falls back to dropship_id if id missing', () => {
      wrapper.vm.handleView({ dropship_id: 456, dropshipNo: 'DS456' })
      expect(mockRouter.push).toHaveBeenCalledWith({ path: '/dropship/orders/456' })
    })

    it('handleReview opens review dialog with selected row id', () => {
      wrapper.vm.handleReview({ id: 77, status: 'pending_review' })
      expect(wrapper.vm.currentReviewId).toBe(77)
      expect(wrapper.vm.reviewDialogVisible).toBe(true)
      expect(wrapper.vm.reviewForm.result).toBe('pass')
      expect(wrapper.vm.reviewForm.remark).toBe('')
    })
  })

  describe('handlePush - golden flow: row action push', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('confirms, calls API, shows success, refreshes list+stats', async () => {
      const row = { id: 88, dropshipNo: 'DS88', status: 'review_pass' }
      await wrapper.vm.handlePush(row)
      await flushPromises()
      expect(wrapper.vm.$confirm).toHaveBeenCalled()
      expect(api.pushDropshipOrder).toHaveBeenCalledWith(88)
      expect(mockMessage.success).toHaveBeenCalledWith('推送成功')
    })

    it('uses dropship_no in confirm message when dropshipNo missing', async () => {
      const row = { id: 99, dropship_no: 'DS-LEGACY-99', status: 'review_pass' }
      await wrapper.vm.handlePush(row)
      await flushPromises()
      expect(api.pushDropshipOrder).toHaveBeenCalledWith(99)
    })
  })

  describe('handleCancel - golden flow: cancel via row action', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('prompts for reason and calls cancel API', async () => {
      jest.spyOn(wrapper.vm, '$prompt').mockResolvedValue({ value: 'Customer request' })
      const row = { id: 33, dropshipNo: 'DS33', status: 'pending_review' }
      await wrapper.vm.handleCancel(row)
      await flushPromises()
      expect(api.cancelDropshipOrder).toHaveBeenCalledWith(33, { reason: 'Customer request' })
      expect(mockMessage.success).toHaveBeenCalledWith('取消成功')
    })
  })

  describe('handleRetry - golden flow: retry via row action', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('confirms and calls retryPushDropshipOrder API', async () => {
      const row = { id: 101, dropshipNo: 'DS101', status: 'push_failed' }
      await wrapper.vm.handleRetry(row)
      await flushPromises()
      expect(wrapper.vm.$confirm).toHaveBeenCalled()
      expect(api.retryPushDropshipOrder).toHaveBeenCalledWith(101)
      expect(mockMessage.success).toHaveBeenCalledWith('重试成功')
    })
  })

  describe('handleBatchReview - golden flow: batch review selected orders', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('confirms then calls batchReviewDropshipOrders with selected ids + pass=true', async () => {
      wrapper.setData({ selectedIds: [1, 2, 3] })
      await wrapper.vm.handleBatchReview()
      await flushPromises()
      expect(wrapper.vm.$confirm).toHaveBeenCalled()
      expect(api.batchReviewDropshipOrders).toHaveBeenCalledWith({
        ids: [1, 2, 3],
        pass: true
      })
      expect(mockMessage.success).toHaveBeenCalledWith('批量审核成功')
    })

    it('fetches list+stats after batch success', async () => {
      wrapper.setData({ selectedIds: [1, 2] })
      const listSpy = jest.spyOn(wrapper.vm, 'fetchList')
      const statsSpy = jest.spyOn(wrapper.vm, 'fetchStats')
      await wrapper.vm.handleBatchReview()
      await flushPromises()
      expect(listSpy).toHaveBeenCalled()
      expect(statsSpy).toHaveBeenCalled()
    })
  })

  describe('handleBatchPush - golden flow: batch push selected orders', () => {
    beforeEach(async () => {
      mountWithMocks()
      await flushPromises()
    })

    it('confirms then calls batchPushDropshipOrders with ids', async () => {
      wrapper.setData({ selectedIds: [10, 20, 30] })
      await wrapper.vm.handleBatchPush()
      await flushPromises()
      expect(wrapper.vm.$confirm).toHaveBeenCalled()
      expect(api.batchPushDropshipOrders).toHaveBeenCalledWith({
        ids: [10, 20, 30]
      })
      expect(mockMessage.success).toHaveBeenCalledWith('批量推送成功')
    })

    it('refreshes list+stats after batch push', async () => {
      wrapper.setData({ selectedIds: [5] })
      const listSpy = jest.spyOn(wrapper.vm, 'fetchList')
      const statsSpy = jest.spyOn(wrapper.vm, 'fetchStats')
      await wrapper.vm.handleBatchPush()
      await flushPromises()
      expect(listSpy).toHaveBeenCalled()
      expect(statsSpy).toHaveBeenCalled()
    })
  })
})
