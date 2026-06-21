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
