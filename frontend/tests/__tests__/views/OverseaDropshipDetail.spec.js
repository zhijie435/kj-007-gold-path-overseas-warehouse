jest.mock('@/api/dropship', () => ({
  getDropshipOrder: jest.fn(() => Promise.resolve({
    data: {
      id: 1,
      dropship_no: 'DS001',
      status: 'pending_review',
      source_channel: 'manual',
      created_at: '2026-06-21 10:00:00',
      created_by: 'admin',
      receiver_name: 'John',
      receiver_phone: '123',
      receiver_country: 'US',
      receiver_state: 'CA',
      receiver_city: 'LA',
      receiver_postal_code: '90001',
      receiver_address: '123 Main St',
      warehouse: { name: 'US Warehouse', code: 'US-LAX' },
      warehouse_id: 1,
      shipping_method_code: 'fedex',
      tracking_no: 'TRK123',
      carrier_name: 'FedEx',
      currency: 'USD',
      subtotal: 50,
      shipping_fee: 10,
      handling_fee: 5,
      insurance_fee: 2,
      duty_fee: 0,
      total_cost: 67,
      declared_value: 50,
      weight: 1.5,
      volume_weight: 2,
      push_attempts: 0,
      items: [
        { sku: 'A', product_name: 'P1', specification: 'S', quantity: 1, unit_price: 50, subtotal: 50, weight: 1, hs_code: '123' }
      ],
      callback_logs: [],
      tracking_events: []
    }
  })),
  reviewDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } })),
  pushDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } })),
  retryPushDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } })),
  cancelDropshipOrder: jest.fn(() => Promise.resolve({ data: { success: true } })),
  updateDropshipOrderStatus: jest.fn(() => Promise.resolve({ data: { success: true } })),
  syncDropshipTracking: jest.fn(() => Promise.resolve({
    data: { success: true, data: { tracking_events: [] } }
  }))
}))

import Vue from 'vue'
import { mount, createLocalVue } from '@vue/test-utils'
import ElementUI from 'element-ui'
import DetailPage from '@/views/OverseaDropship/Detail.vue'

Vue.use(ElementUI)

const buildMockMessage = () => {
  const fn = jest.fn()
  fn.success = jest.fn()
  fn.warning = jest.fn()
  fn.error = jest.fn()
  fn.info = jest.fn()
  return fn
}

const flushPromises = () => new Promise(resolve => setTimeout(resolve, 0))

describe('OverseaDropship/Detail.vue - Status steps and transitions', () => {
  let wrapper
  let mockRouter
  let mockMessage

  const mountWithStatus = (status, extra = {}) => {
    const localVue = createLocalVue()
    localVue.use(ElementUI)
    mockRouter = { back: jest.fn(), push: jest.fn() }
    mockMessage = buildMockMessage()
    const mockLoading = jest.fn(() => ({ close: jest.fn() }))
    return mount(DetailPage, {
      localVue,
      mocks: {
        $router: mockRouter,
        $route: { params: { id: '1' } },
        $message: mockMessage,
        $confirm: jest.fn(() => Promise.resolve()),
        $loading: mockLoading
      }
    })
  }

  afterEach(() => {
    if (wrapper) wrapper.destroy()
  })

  describe('currentStepIndex computed', () => {
    it('maps draft to step 1', () => {
      wrapper = mountWithStatus('draft')
      wrapper.setData({ detail: { status: 'draft', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(1)
    })

    it('maps pending_review to step 2', () => {
      wrapper = mountWithStatus('pending_review')
      wrapper.setData({ detail: { status: 'pending_review', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(2)
    })

    it('maps review_pass to step 3', () => {
      wrapper = mountWithStatus('review_pass')
      wrapper.setData({ detail: { status: 'review_pass', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(3)
    })

    it('maps pushing to step 4', () => {
      wrapper = mountWithStatus('pushing')
      wrapper.setData({ detail: { status: 'pushing', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(4)
    })

    it('maps processing to step 5', () => {
      wrapper = mountWithStatus('processing')
      wrapper.setData({ detail: { status: 'processing', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(5)
    })

    it('maps shipped to step 6', () => {
      wrapper = mountWithStatus('shipped')
      wrapper.setData({ detail: { status: 'shipped', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(6)
    })

    it('maps in_transit to step 7', () => {
      wrapper = mountWithStatus('in_transit')
      wrapper.setData({ detail: { status: 'in_transit', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(7)
    })

    it('maps delivered to step 8', () => {
      wrapper = mountWithStatus('delivered')
      wrapper.setData({ detail: { status: 'delivered', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(8)
    })

    it('maps completed to step 9', () => {
      wrapper = mountWithStatus('completed')
      wrapper.setData({ detail: { status: 'completed', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(9)
    })

    it('maps auto_review_pass to step 3 (same as review_pass)', () => {
      wrapper = mountWithStatus('auto_review_pass')
      wrapper.setData({ detail: { status: 'auto_review_pass', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(3)
    })

    it('maps push_success, picked, packed, exception to step 5', () => {
      const statuses = ['push_success', 'picked', 'packed', 'exception']
      statuses.forEach(status => {
        wrapper = mountWithStatus(status)
        wrapper.setData({ detail: { status, items: [] } })
        expect(wrapper.vm.currentStepIndex).toBe(5)
      })
    })

    it('maps push_failed to step 4', () => {
      wrapper = mountWithStatus('push_failed')
      wrapper.setData({ detail: { status: 'push_failed', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(4)
    })

    it('maps customs to step 7', () => {
      wrapper = mountWithStatus('customs')
      wrapper.setData({ detail: { status: 'customs', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(7)
    })

    it('maps review_reject to step 1', () => {
      wrapper = mountWithStatus('review_reject')
      wrapper.setData({ detail: { status: 'review_reject', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(1)
    })

    it('maps cancelled to step 0', () => {
      wrapper = mountWithStatus('cancelled')
      wrapper.setData({ detail: { status: 'cancelled', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(0)
    })

    it('maps returned to step 8', () => {
      wrapper = mountWithStatus('returned')
      wrapper.setData({ detail: { status: 'returned', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(8)
    })

    it('unknown status defaults to 0', () => {
      wrapper = mountWithStatus('unknown')
      wrapper.setData({ detail: { status: 'completely_made_up', items: [] } })
      expect(wrapper.vm.currentStepIndex).toBe(0)
    })
  })

  describe('canTransitionTo method', () => {
    it('draft can transition to pending_review and cancelled', () => {
      wrapper = mountWithStatus('draft')
      wrapper.setData({ detail: { status: 'draft', items: [] } })
      expect(wrapper.vm.canTransitionTo('pending_review')).toBe(true)
      expect(wrapper.vm.canTransitionTo('cancelled')).toBe(true)
      expect(wrapper.vm.canTransitionTo('processing')).toBe(false)
    })

    it('pending_review can transition to review_pass/review_reject/cancelled', () => {
      wrapper = mountWithStatus('pending_review')
      wrapper.setData({ detail: { status: 'pending_review', items: [] } })
      expect(wrapper.vm.canTransitionTo('review_pass')).toBe(true)
      expect(wrapper.vm.canTransitionTo('auto_review_pass')).toBe(true)
      expect(wrapper.vm.canTransitionTo('review_reject')).toBe(true)
      expect(wrapper.vm.canTransitionTo('cancelled')).toBe(true)
      expect(wrapper.vm.canTransitionTo('pushing')).toBe(false)
    })

    it('review_pass can transition to pushing or cancelled', () => {
      wrapper = mountWithStatus('review_pass')
      wrapper.setData({ detail: { status: 'review_pass', items: [] } })
      expect(wrapper.vm.canTransitionTo('pushing')).toBe(true)
      expect(wrapper.vm.canTransitionTo('cancelled')).toBe(true)
      expect(wrapper.vm.canTransitionTo('processing')).toBe(false)
    })

    it('push_success can transition to processing or exception', () => {
      wrapper = mountWithStatus('push_success')
      wrapper.setData({ detail: { status: 'push_success', items: [] } })
      expect(wrapper.vm.canTransitionTo('processing')).toBe(true)
      expect(wrapper.vm.canTransitionTo('exception')).toBe(true)
      expect(wrapper.vm.canTransitionTo('shipped')).toBe(false)
    })

    it('processing can transition to picked, exception, or cancelled', () => {
      wrapper = mountWithStatus('processing')
      wrapper.setData({ detail: { status: 'processing', items: [] } })
      expect(wrapper.vm.canTransitionTo('picked')).toBe(true)
      expect(wrapper.vm.canTransitionTo('exception')).toBe(true)
      expect(wrapper.vm.canTransitionTo('cancelled')).toBe(true)
      expect(wrapper.vm.canTransitionTo('packed')).toBe(false)
    })

    it('shipped can transition to in_transit, customs, delivered, returned, exception', () => {
      wrapper = mountWithStatus('shipped')
      wrapper.setData({ detail: { status: 'shipped', items: [] } })
      expect(wrapper.vm.canTransitionTo('in_transit')).toBe(true)
      expect(wrapper.vm.canTransitionTo('customs')).toBe(true)
      expect(wrapper.vm.canTransitionTo('delivered')).toBe(true)
      expect(wrapper.vm.canTransitionTo('returned')).toBe(true)
      expect(wrapper.vm.canTransitionTo('exception')).toBe(true)
      expect(wrapper.vm.canTransitionTo('completed')).toBe(false)
    })

    it('completed is terminal - cannot transition', () => {
      wrapper = mountWithStatus('completed')
      wrapper.setData({ detail: { status: 'completed', items: [] } })
      expect(wrapper.vm.canTransitionTo('any')).toBe(false)
      expect(wrapper.vm.canTransitionTo('cancelled')).toBe(false)
      expect(wrapper.vm.canTransitionTo('completed')).toBe(false)
    })

    it('cancelled is terminal - cannot transition', () => {
      wrapper = mountWithStatus('cancelled')
      wrapper.setData({ detail: { status: 'cancelled', items: [] } })
      expect(wrapper.vm.canTransitionTo('draft')).toBe(false)
      expect(wrapper.vm.canTransitionTo('pending_review')).toBe(false)
    })

    it('exception can transition back to processing, cancelled, or pushing', () => {
      wrapper = mountWithStatus('exception')
      wrapper.setData({ detail: { status: 'exception', items: [] } })
      expect(wrapper.vm.canTransitionTo('processing')).toBe(true)
      expect(wrapper.vm.canTransitionTo('cancelled')).toBe(true)
      expect(wrapper.vm.canTransitionTo('pushing')).toBe(true)
      expect(wrapper.vm.canTransitionTo('shipped')).toBe(false)
    })
  })

  describe('canReview computed', () => {
    it('is true when status allows review_pass or review_reject transition', () => {
      wrapper = mountWithStatus('pending_review')
      wrapper.setData({ detail: { status: 'pending_review', items: [] } })
      expect(wrapper.vm.canReview).toBe(true)
    })

    it('is false for already reviewed statuses', () => {
      wrapper = mountWithStatus('review_pass')
      wrapper.setData({ detail: { status: 'review_pass', items: [] } })
      expect(wrapper.vm.canReview).toBe(false)
    })
  })

  describe('canPush computed', () => {
    it('is true for review_pass (can transition to pushing)', () => {
      wrapper = mountWithStatus('review_pass')
      wrapper.setData({ detail: { status: 'review_pass', items: [] } })
      expect(wrapper.vm.canPush).toBe(true)
    })

    it('is true for auto_review_pass', () => {
      wrapper = mountWithStatus('auto_review_pass')
      wrapper.setData({ detail: { status: 'auto_review_pass', items: [] } })
      expect(wrapper.vm.canPush).toBe(true)
    })

    it('is false for already pushing status', () => {
      wrapper = mountWithStatus('pushing')
      wrapper.setData({ detail: { status: 'pushing', items: [] } })
      expect(wrapper.vm.canPush).toBe(false)
    })
  })

  describe('canRetry computed', () => {
    it('is true for push_failed (can transition to pushing + status is in retry-list)', () => {
      wrapper = mountWithStatus('push_failed')
      wrapper.setData({ detail: { status: 'push_failed', items: [] } })
      expect(wrapper.vm.canRetry).toBe(true)
    })

    it('is true for exception status', () => {
      wrapper = mountWithStatus('exception')
      wrapper.setData({ detail: { status: 'exception', items: [] } })
      expect(wrapper.vm.canRetry).toBe(true)
    })

    it('is false for review_pass (can push but not retry)', () => {
      wrapper = mountWithStatus('review_pass')
      wrapper.setData({ detail: { status: 'review_pass', items: [] } })
      expect(wrapper.vm.canRetry).toBe(false)
    })
  })

  describe('canCancel computed', () => {
    it('is true for cancellable statuses', () => {
      const cancellable = ['draft', 'pending_review', 'auto_review_pass', 'review_pass', 'processing', 'exception']
      cancellable.forEach(status => {
        wrapper = mountWithStatus(status)
        wrapper.setData({ detail: { status, items: [] } })
        expect(wrapper.vm.canCancel).toBe(true)
      })
    })

    it('is false for terminal and non-cancellable statuses', () => {
      const notCancellable = ['completed', 'cancelled', 'returned', 'review_reject', 'shipped', 'in_transit']
      notCancellable.forEach(status => {
        wrapper = mountWithStatus(status)
        wrapper.setData({ detail: { status, items: [] } })
        expect(wrapper.vm.canCancel).toBe(false)
      })
    })
  })

  describe('canUpdateStatus computed', () => {
    it('is true for fulfillment-phase statuses', () => {
      const updatable = ['push_success', 'processing', 'picked', 'packed', 'shipped', 'in_transit', 'customs', 'delivered']
      updatable.forEach(status => {
        wrapper = mountWithStatus(status)
        wrapper.setData({ detail: { status, items: [] } })
        expect(wrapper.vm.canUpdateStatus).toBe(true)
      })
    })

    it('is false for earlier or terminal statuses', () => {
      const notUpdatable = ['draft', 'pending_review', 'review_pass', 'completed', 'cancelled', 'returned', 'exception']
      notUpdatable.forEach(status => {
        wrapper = mountWithStatus(status)
        wrapper.setData({ detail: { status, items: [] } })
        expect(wrapper.vm.canUpdateStatus).toBe(false)
      })
    })
  })

  describe('itemSummary computed', () => {
    it('calculates total rows and qty from items', () => {
      wrapper = mountWithStatus('draft')
      wrapper.setData({
        detail: {
          status: 'draft',
          items: [
            { quantity: 2 },
            { quantity: 3 },
            { quantity: 1 }
          ]
        }
      })
      expect(wrapper.vm.itemSummary.totalRows).toBe(3)
      expect(wrapper.vm.itemSummary.totalQty).toBe(6)
    })

    it('handles empty items list', () => {
      wrapper = mountWithStatus('draft')
      wrapper.setData({ detail: { status: 'draft', items: [] } })
      expect(wrapper.vm.itemSummary.totalRows).toBe(0)
      expect(wrapper.vm.itemSummary.totalQty).toBe(0)
    })
  })

  describe('getStatusLabel', () => {
    it('returns correct Chinese labels', () => {
      wrapper = mountWithStatus('draft')
      expect(wrapper.vm.getStatusLabel('draft')).toBe('草稿')
      expect(wrapper.vm.getStatusLabel('pending_review')).toBe('待审核')
      expect(wrapper.vm.getStatusLabel('review_pass')).toBe('审核通过')
      expect(wrapper.vm.getStatusLabel('review_reject')).toBe('审核拒绝')
      expect(wrapper.vm.getStatusLabel('push_success')).toBe('推单成功')
      expect(wrapper.vm.getStatusLabel('push_failed')).toBe('推单失败')
      expect(wrapper.vm.getStatusLabel('in_transit')).toBe('运输中')
      expect(wrapper.vm.getStatusLabel('completed')).toBe('已完成')
      expect(wrapper.vm.getStatusLabel('cancelled')).toBe('已取消')
    })

    it('returns original value for unknown status', () => {
      wrapper = mountWithStatus('draft')
      expect(wrapper.vm.getStatusLabel('mystery_status')).toBe('mystery_status')
    })
  })

  describe('getStepStatus', () => {
    it('returns success for steps before current', () => {
      wrapper = mountWithStatus('processing')
      wrapper.setData({ detail: { status: 'processing', items: [] } })
      expect(wrapper.vm.getStepStatus(0)).toBe('success')
      expect(wrapper.vm.getStepStatus(3)).toBe('success')
    })

    it('returns process for the current step index', () => {
      wrapper = mountWithStatus('processing')
      wrapper.setData({ detail: { status: 'processing', items: [] } })
      expect(wrapper.vm.getStepStatus(4)).toBe('process')
    })

    it('returns empty string for steps after current', () => {
      wrapper = mountWithStatus('processing')
      wrapper.setData({ detail: { status: 'processing', items: [] } })
      expect(wrapper.vm.getStepStatus(5)).toBe('')
      expect(wrapper.vm.getStepStatus(8)).toBe('')
    })
  })

  describe('getLogType', () => {
    it('maps log levels correctly', () => {
      wrapper = mountWithStatus('draft')
      expect(wrapper.vm.getLogType('success')).toBe('success')
      expect(wrapper.vm.getLogType('warning')).toBe('warning')
      expect(wrapper.vm.getLogType('danger')).toBe('danger')
      expect(wrapper.vm.getLogType('primary')).toBe('primary')
      expect(wrapper.vm.getLogType('info')).toBe('info')
    })

    it('defaults to info', () => {
      wrapper = mountWithStatus('draft')
      expect(wrapper.vm.getLogType('unknown')).toBe('info')
    })
  })

  describe('getCountryName', () => {
    it('returns flag and name', () => {
      wrapper = mountWithStatus('draft')
      expect(wrapper.vm.getCountryName('US')).toBe('🇺🇸 美国')
      expect(wrapper.vm.getCountryName('GB')).toBe('🇬🇧 英国')
      expect(wrapper.vm.getCountryName('DE')).toBe('🇩🇪 德国')
      expect(wrapper.vm.getCountryName('JP')).toBe('🇯🇵 日本')
    })

    it('returns code for unknown country', () => {
      wrapper = mountWithStatus('draft')
      expect(wrapper.vm.getCountryName('XX')).toBe('XX')
    })
  })

  describe('handleStatusCommand validation', () => {
    it('warns when trying to transition to invalid status', () => {
      wrapper = mountWithStatus('completed')
      wrapper.setData({ detail: { status: 'completed', items: [] } })
      wrapper.vm.handleStatusCommand('processing')
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith(
        expect.stringContaining('当前状态不允许切换')
      )
    })
  })

  describe('handleCancel validation', () => {
    it('warns when trying to cancel non-cancellable status', () => {
      wrapper = mountWithStatus('completed')
      wrapper.setData({ detail: { status: 'completed', items: [] } })
      wrapper.vm.handleCancel()
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith(
        expect.stringContaining('当前状态不允许取消')
      )
    })
  })

  describe('handleRetry validation', () => {
    it('warns when retrying from non-retryable status', async () => {
      wrapper = mountWithStatus('completed')
      wrapper.setData({ detail: { status: 'completed', items: [] } })
      wrapper.vm.handleRetry()
      await flushPromises()
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith(
        expect.stringContaining('当前状态不允许重试')
      )
    })
  })

  describe('handlePush validation', () => {
    it('warns when pushing from non-pushable status', async () => {
      wrapper = mountWithStatus('draft')
      wrapper.setData({ detail: { status: 'draft', items: [] } })
      try {
        wrapper.vm.handlePush()
      } catch (e) {}
      await flushPromises()
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith(
        expect.stringContaining('当前状态不允许推送')
      )
    })
  })

  describe('handleBack', () => {
    it('calls router.back()', () => {
      wrapper = mountWithStatus('draft')
      wrapper.vm.handleBack()
      expect(wrapper.vm.$router.back).toHaveBeenCalled()
    })
  })
})
