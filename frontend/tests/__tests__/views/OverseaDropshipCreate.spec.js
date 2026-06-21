jest.mock('@/api/dropship', () => ({
  createDropshipOrder: jest.fn(() => Promise.resolve({
    data: { success: true, data: { dropship_no: 'DS202606210001' } }
  }))
}))

import Vue from 'vue'
import { mount, createLocalVue } from '@vue/test-utils'
import ElementUI from 'element-ui'
import CreatePage from '@/views/OverseaDropship/Create.vue'

Vue.use(ElementUI)

const buildMockMessage = () => {
  const fn = jest.fn()
  fn.success = jest.fn()
  fn.warning = jest.fn()
  fn.error = jest.fn()
  fn.info = jest.fn()
  return fn
}

describe('OverseaDropship/Create.vue - Wizard and validation', () => {
  let wrapper
  let mockRouter
  let mockMessage

  beforeEach(() => {
    const localVue = createLocalVue()
    localVue.use(ElementUI)
    mockRouter = { back: jest.fn(), push: jest.fn() }
    mockMessage = buildMockMessage()
    wrapper = mount(CreatePage, {
      localVue,
      mocks: {
        $router: mockRouter,
        $message: mockMessage,
        $confirm: jest.fn(() => Promise.resolve()),
        $refs: { receiverForm: { validate: jest.fn((cb) => cb(true)) } }
      }
    })
  })

  afterEach(() => {
    wrapper.destroy()
  })

  describe('wizard steps navigation', () => {
    it('starts at step 0 (receiver info)', () => {
      expect(wrapper.vm.activeStep).toBe(0)
    })

    it('goToStep changes activeStep directly', () => {
      wrapper.vm.goToStep(2)
      expect(wrapper.vm.activeStep).toBe(2)
      wrapper.vm.goToStep(3)
      expect(wrapper.vm.activeStep).toBe(3)
    })

    it('prevStep decrements step when > 0', () => {
      wrapper.setData({ activeStep: 2 })
      wrapper.vm.prevStep()
      expect(wrapper.vm.activeStep).toBe(1)
      wrapper.vm.prevStep()
      expect(wrapper.vm.activeStep).toBe(0)
    })

    it('prevStep does nothing when already at step 0', () => {
      wrapper.setData({ activeStep: 0 })
      wrapper.vm.prevStep()
      expect(wrapper.vm.activeStep).toBe(0)
    })

    it('nextStep for step 1 validates no empty product list', async () => {
      wrapper.setData({
        activeStep: 1,
        productItems: []
      })
      wrapper.vm.nextStep(1)
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith('请至少添加一件商品')
      expect(wrapper.vm.activeStep).toBe(1)
    })

    it('nextStep for step 1 validates SKU, name and quantity', () => {
      wrapper.setData({
        activeStep: 1,
        productItems: [
          { sku: '', name: '', quantity: 0, price: 0, subtotal: 0, weight: 0 }
        ]
      })
      wrapper.vm.nextStep(1)
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith('请完整填写商品信息（SKU、名称、数量必填）')
      expect(wrapper.vm.activeStep).toBe(1)
    })

    it('nextStep for step 1 advances when products are valid', () => {
      wrapper.setData({
        activeStep: 1,
        productItems: [
          { sku: 'SKU001', name: 'Product', quantity: 2, price: 10, subtotal: 20, weight: 0.5 }
        ]
      })
      wrapper.vm.nextStep(1)
      expect(wrapper.vm.activeStep).toBe(2)
    })

    it('nextStep for step 2 validates warehouse is selected', () => {
      wrapper.setData({
        activeStep: 2,
        shippingForm: {
          warehouseId: null,
          shippingMethod: 'fedex_ground'
        }
      })
      wrapper.vm.nextStep(2)
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith('请选择发货海外仓')
      expect(wrapper.vm.activeStep).toBe(2)
    })

    it('nextStep for step 2 validates shipping method', () => {
      wrapper.setData({
        activeStep: 2,
        shippingForm: {
          warehouseId: 1,
          shippingMethod: ''
        }
      })
      wrapper.vm.nextStep(2)
      expect(wrapper.vm.$message.warning).toHaveBeenCalledWith('请选择物流渠道')
      expect(wrapper.vm.activeStep).toBe(2)
    })

    it('nextStep for step 2 advances when both warehouse and method set', () => {
      wrapper.setData({
        activeStep: 2,
        productItems: [
          { sku: 'A', name: 'P1', quantity: 1, price: 10, subtotal: 10, weight: 1 }
        ],
        shippingForm: {
          warehouseId: 1,
          shippingMethod: 'fedex_ground',
          insuranceEnabled: false,
          declaredValue: 0,
          currency: 'USD',
          handlingFee: 5,
          shippingFee: 10,
          insuranceFee: 0
        }
      })
      wrapper.vm.nextStep(2)
      expect(wrapper.vm.activeStep).toBe(3)
    })
  })

  describe('computed properties', () => {
    beforeEach(() => {
      wrapper.setData({
        productItems: [
          { sku: 'A', name: 'P1', quantity: 2, price: 15.5, subtotal: 31, weight: 0.5 },
          { sku: 'B', name: 'P2', quantity: 1, price: 20, subtotal: 20, weight: 1 }
        ],
        shippingForm: {
          warehouseId: 1,
          shippingMethod: 'fedex',
          insuranceEnabled: false,
          declaredValue: 51,
          currency: 'USD',
          handlingFee: 5,
          shippingFee: 12.5,
          insuranceFee: 0
        }
      })
    })

    it('totalQuantity sums all item quantities', () => {
      expect(wrapper.vm.totalQuantity).toBe(3)
    })

    it('totalWeight sums (weight * quantity) for each item', () => {
      expect(wrapper.vm.totalWeight).toBeCloseTo(2, 3)
    })

    it('totalProductAmount sums all subtotals', () => {
      expect(wrapper.vm.totalProductAmount).toBe(51)
    })

    it('totalCost includes product, handling, shipping (no insurance)', () => {
      expect(wrapper.vm.totalCost).toBe(68.5)
    })

    it('totalCost includes insurance when enabled', () => {
      wrapper.setData({
        shippingForm: {
          ...wrapper.vm.shippingForm,
          insuranceEnabled: true,
          insuranceFee: 2.5
        }
      })
      expect(wrapper.vm.totalCost).toBe(71)
    })
  })

  describe('product items management', () => {
    it('addItemRow appends a new empty item', () => {
      const initialLength = wrapper.vm.productItems.length
      wrapper.vm.addItemRow()
      expect(wrapper.vm.productItems.length).toBe(initialLength + 1)
      const last = wrapper.vm.productItems[wrapper.vm.productItems.length - 1]
      expect(last.quantity).toBe(1)
      expect(last.price).toBe(0)
      expect(last.subtotal).toBe(0)
    })

    it('addItemRow with skuSearch fills SKU field', () => {
      wrapper.setData({ skuSearch: 'NEWSKU-789' })
      wrapper.vm.addItemRow()
      const last = wrapper.vm.productItems[wrapper.vm.productItems.length - 1]
      expect(last.sku).toBe('NEWSKU-789')
      expect(wrapper.vm.skuSearch).toBe('')
    })

    it('removeItemRow removes item at index', () => {
      wrapper.setData({
        productItems: [
          { sku: 'A' },
          { sku: 'B' },
          { sku: 'C' }
        ]
      })
      wrapper.vm.removeItemRow(1)
      expect(wrapper.vm.productItems.map(i => i.sku)).toEqual(['A', 'C'])
    })

    it('calcItemSubtotal correctly calculates price * quantity', () => {
      wrapper.setData({
        productItems: [
          { sku: 'X', price: 12.5, quantity: 3, subtotal: 0 }
        ]
      })
      wrapper.vm.calcItemSubtotal(0)
      expect(wrapper.vm.productItems[0].subtotal).toBe(37.5)
    })

    it('calcItemSubtotal rounds to 2 decimals', () => {
      wrapper.setData({
        productItems: [
          { sku: 'X', price: 2.5, quantity: 3, subtotal: 0 }
        ]
      })
      wrapper.vm.calcItemSubtotal(0)
      expect(wrapper.vm.productItems[0].subtotal).toBe(7.5)
    })
  })

  describe('insurance and cost calculation', () => {
    it('calcTotalCost auto-calculates 2% insurance when enabled and fee is 0', () => {
      wrapper.setData({
        productItems: [
          { sku: 'A', quantity: 1, price: 100, subtotal: 100 }
        ],
        shippingForm: {
          insuranceEnabled: true,
          insuranceFee: 0,
          handlingFee: 0,
          shippingFee: 0
        }
      })
      wrapper.vm.calcTotalCost()
      expect(wrapper.vm.shippingForm.insuranceFee).toBe(2)
    })

    it('calcTotalCost does not overwrite non-zero insurance fee', () => {
      wrapper.setData({
        productItems: [
          { sku: 'A', quantity: 1, price: 100, subtotal: 100 }
        ],
        shippingForm: {
          insuranceEnabled: true,
          insuranceFee: 5,
          handlingFee: 0,
          shippingFee: 0
        }
      })
      wrapper.vm.calcTotalCost()
      expect(wrapper.vm.shippingForm.insuranceFee).toBe(5)
    })
  })

  describe('recommended warehouses', () => {
    it('recommendedWarehouses sorts by recommended flag when country is set', () => {
      wrapper.setData({
        receiverForm: { country: 'US' }
      })
      const result = wrapper.vm.recommendedWarehouses
      expect(result[0].recommended).toBe(true)
      expect(['US', 'CA']).toContain(wrapper.vm.receiverForm.country)
    })

    it('recommendedWarehouses has no recommendations when country is empty', () => {
      wrapper.setData({ receiverForm: { country: '' } })
      const allFalse = wrapper.vm.recommendedWarehouses.every(w => w.recommended === false)
      expect(allFalse).toBe(true)
    })

    it('handleCountryChange auto-selects recommended warehouse', () => {
      wrapper.setData({
        receiverForm: { country: 'JP' },
        shippingForm: { warehouseId: null }
      })
      wrapper.vm.handleCountryChange()
      const jpWarehouse = wrapper.vm.warehouseList.find(w => w.countries.includes('JP'))
      expect(wrapper.vm.shippingForm.warehouseId).toBe(jpWarehouse.id)
    })
  })

  describe('helper methods', () => {
    it('formatDate returns YYYYMMDDHHmmss format', () => {
      const date = new Date('2026-06-21T10:30:45')
      const formatted = wrapper.vm.formatDate(date)
      expect(formatted).toBe('20260621103045')
    })

    it('getCountryName returns flag + name for known country', () => {
      expect(wrapper.vm.getCountryName('US')).toBe('🇺🇸 美国')
      expect(wrapper.vm.getCountryName('GB')).toBe('🇬🇧 英国')
      expect(wrapper.vm.getCountryName('JP')).toBe('🇯🇵 日本')
    })

    it('getCountryName returns code for unknown country', () => {
      expect(wrapper.vm.getCountryName('ZZ')).toBe('ZZ')
    })

    it('getWarehouseName returns name for known id', () => {
      expect(wrapper.vm.getWarehouseName(1)).toBe('美国洛杉矶仓')
      expect(wrapper.vm.getWarehouseName(5)).toBe('日本东京仓')
    })

    it('getWarehouseName returns dash for unknown id', () => {
      expect(wrapper.vm.getWarehouseName(999)).toBe('-')
    })

    it('getShippingName returns mapped name', () => {
      expect(wrapper.vm.getShippingName('dhl_express')).toBe('DHL Express')
      expect(wrapper.vm.getShippingName('fedex_ground')).toBe('FedEx Ground')
    })

    it('getShippingName returns value for unknown shipping method', () => {
      expect(wrapper.vm.getShippingName('unknown_carrier')).toBe('unknown_carrier')
    })
  })

  describe('buildOrderData', () => {
    beforeEach(() => {
      wrapper.setData({
        receiverForm: {
          name: 'John',
          phone: '+123',
          email: 'j@e.com',
          country: 'US',
          state: 'CA',
          city: 'LA',
          postalCode: '90001',
          address: '123 Main St'
        },
        productItems: [
          {
            sku: 'SKU1', name: 'P1', spec: 'S', quantity: 2, price: 25,
            weight: 0.5, hsCode: '123', batchNo: 'B1'
          }
        ],
        shippingForm: {
          warehouseId: 1,
          shippingMethod: 'fedex',
          declaredValue: 50,
          currency: 'USD',
          handlingFee: 5,
          shippingFee: 10,
          insuranceEnabled: true,
          insuranceFee: 1
        },
        submitRemark: 'Test order'
      })
    })

    it('buildOrderData maps fields correctly', () => {
      const data = wrapper.vm.buildOrderData(false)
      expect(data.receiver_name).toBe('John')
      expect(data.receiver_country).toBe('US')
      expect(data.warehouse_id).toBe(1)
      expect(data.shipping_method_code).toBe('fedex')
      expect(data.currency).toBe('USD')
      expect(data.declared_value).toBe(50)
      expect(data.submit_now).toBe(false)
      expect(data.items.length).toBe(1)
      expect(data.items[0].sku).toBe('SKU1')
      expect(data.items[0].unit_price).toBe(25)
      expect(data.items[0].quantity).toBe(2)
    })

    it('buildOrderData with submitNow=true sets submit_now flag', () => {
      const data = wrapper.vm.buildOrderData(true)
      expect(data.submit_now).toBe(true)
    })

    it('buildOrderData calculates unit_cost as 70% of unit_price', () => {
      const data = wrapper.vm.buildOrderData(false)
      expect(data.items[0].unit_cost).toBeCloseTo(17.5, 2)
    })
  })
})

import { createDropshipOrder } from '@/api/dropship'

describe('OverseaDropship/Create.vue - Golden Path: Full Interaction Flows', () => {
  let wrapper
  let mockRouter
  let mockMessage
  let mockConfirm

  const mountWithMocks = (confirmResolved = Promise.resolve()) => {
    const localVue = createLocalVue()
    localVue.use(ElementUI)
    mockRouter = { back: jest.fn(), push: jest.fn() }
    mockMessage = buildMockMessage()
    mockConfirm = jest.fn(() => confirmResolved)
    wrapper = mount(CreatePage, {
      localVue,
      mocks: {
        $router: mockRouter,
        $message: mockMessage,
        $confirm: mockConfirm,
        $refs: { receiverForm: { validate: jest.fn((cb) => cb(true)) } }
      }
    })
    wrapper.setData({
      receiverForm: {
        name: 'John Doe',
        phone: '+15551234567',
        email: 'john@example.com',
        country: 'US',
        state: 'CA',
        city: 'Los Angeles',
        postalCode: '90001',
        address: '123 Sunset Blvd'
      },
      productItems: [
        { sku: 'SKU-001', name: 'Wireless Headphone', spec: 'Black', quantity: 2, price: 49.99, subtotal: 99.98, weight: 0.3, hsCode: '851830', batchNo: 'B2026' }
      ],
      shippingForm: {
        warehouseId: 1,
        shippingMethod: 'fedex_ground',
        declaredValue: 99.98,
        currency: 'USD',
        handlingFee: 3,
        shippingFee: 8.5,
        insuranceEnabled: true,
        insuranceFee: 2
      },
      submitRemark: 'Urgent order'
    })
    jest.clearAllMocks()
    createDropshipOrder.mockClear()
  }

  afterEach(() => {
    if (wrapper) wrapper.destroy()
  })

  describe('handleSaveDraft', () => {
    it('golden path: confirm → buildOrderData(submit_now=false) → API → success message → navigate to list', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockResolvedValue({
        data: { success: true, data: { dropship_no: 'DS202606210099' } }
      })

      wrapper.vm.handleSaveDraft()
      await wrapper.vm.$nextTick()

      expect(mockConfirm).toHaveBeenCalledWith(
        '确认保存为草稿吗？保存后可以后续再提交审核。',
        '保存确认',
        expect.objectContaining({ confirmButtonText: '保存草稿', type: 'info' })
      )
      expect(wrapper.vm.submitLoading).toBe(true)

      await Promise.resolve()
      await wrapper.vm.$nextTick()

      expect(createDropshipOrder).toHaveBeenCalledTimes(1)
      const payload = createDropshipOrder.mock.calls[0][0]
      expect(payload.submit_now).toBe(false)
      expect(payload.receiver_name).toBe('John Doe')
      expect(payload.warehouse_id).toBe(1)
      expect(payload.items.length).toBe(1)
      expect(payload.items[0].sku).toBe('SKU-001')

      await wrapper.vm.$nextTick()
      expect(wrapper.vm.submitLoading).toBe(false)
      expect(mockMessage.success).toHaveBeenCalledWith('代发单【DS202606210099】已保存为草稿')
      expect(mockRouter.push).toHaveBeenCalledWith({ path: '/dropship/orders' })
    })

    it('user cancels the confirm dialog: no API call, no navigation', async () => {
      mountWithMocks(Promise.reject())
      wrapper.vm.handleSaveDraft()
      await wrapper.vm.$nextTick()
      await Promise.resolve().catch(() => {})
      await wrapper.vm.$nextTick()
      expect(createDropshipOrder).not.toHaveBeenCalled()
      expect(mockRouter.push).not.toHaveBeenCalled()
      expect(wrapper.vm.submitLoading).toBe(false)
    })

    it('API returns success=false: shows error message with server message', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockResolvedValue({
        data: { success: false, message: '商品SKU不存在' }
      })

      wrapper.vm.handleSaveDraft()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.submitLoading).toBe(false)
      expect(mockMessage.error).toHaveBeenCalledWith('商品SKU不存在')
      expect(mockRouter.push).not.toHaveBeenCalled()
    })

    it('API returns success=false without message: shows default error', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockResolvedValue({ data: { success: false } })

      wrapper.vm.handleSaveDraft()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(mockMessage.error).toHaveBeenCalledWith('保存失败')
    })

    it('API throws Network Error: catch block shows error.message', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockRejectedValue(new Error('Network Error'))

      wrapper.vm.handleSaveDraft()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      try { await Promise.resolve() } catch (e) {}
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.submitLoading).toBe(false)
      expect(mockMessage.error).toHaveBeenCalledWith('Network Error')
    })

    it('API throws error without message: shows default 保存失败', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockRejectedValue({})

      wrapper.vm.handleSaveDraft()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(mockMessage.error).toHaveBeenCalledWith('保存失败')
    })
  })

  describe('handleSubmit', () => {
    it('golden path: confirm → buildOrderData(submit_now=true) → API → success message → navigate to list', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockResolvedValue({
        data: { success: true, data: { dropship_no: 'DS202606210100' } }
      })

      wrapper.vm.handleSubmit()
      await wrapper.vm.$nextTick()

      expect(mockConfirm).toHaveBeenCalledWith(
        '确认提交此代发单吗？提交后将进入审核流程，可能会触发自动化规则。',
        '提交确认',
        expect.objectContaining({ confirmButtonText: '确认提交', type: 'success' })
      )
      expect(wrapper.vm.submitLoading).toBe(true)

      await Promise.resolve()
      await wrapper.vm.$nextTick()

      expect(createDropshipOrder).toHaveBeenCalledTimes(1)
      const payload = createDropshipOrder.mock.calls[0][0]
      expect(payload.submit_now).toBe(true)
      expect(payload.remark).toBe('Urgent order')
      expect(payload.shipping_method_code).toBe('fedex_ground')

      await wrapper.vm.$nextTick()
      expect(wrapper.vm.submitLoading).toBe(false)
      expect(mockMessage.success).toHaveBeenCalledWith('代发单【DS202606210100】创建成功，已提交审核')
      expect(mockRouter.push).toHaveBeenCalledWith({ path: '/dropship/orders' })
    })

    it('user cancels the confirm dialog: no API call, no navigation', async () => {
      mountWithMocks(Promise.reject())
      wrapper.vm.handleSubmit()
      await wrapper.vm.$nextTick()
      await Promise.resolve().catch(() => {})
      await wrapper.vm.$nextTick()
      expect(createDropshipOrder).not.toHaveBeenCalled()
      expect(mockRouter.push).not.toHaveBeenCalled()
      expect(wrapper.vm.submitLoading).toBe(false)
    })

    it('API returns success=false with message: shows server error', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockResolvedValue({
        data: { success: false, message: '仓库库存不足' }
      })

      wrapper.vm.handleSubmit()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(mockMessage.error).toHaveBeenCalledWith('仓库库存不足')
      expect(mockRouter.push).not.toHaveBeenCalled()
    })

    it('API throws exception: catch block shows error', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockRejectedValue(new Error('500 Internal Server Error'))

      wrapper.vm.handleSubmit()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.submitLoading).toBe(false)
      expect(mockMessage.error).toHaveBeenCalledWith('500 Internal Server Error')
    })

    it('API throws exception without message: shows default 提交失败', async () => {
      mountWithMocks(Promise.resolve())
      createDropshipOrder.mockRejectedValue({})

      wrapper.vm.handleSubmit()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      await wrapper.vm.$nextTick()
      await wrapper.vm.$nextTick()

      expect(mockMessage.error).toHaveBeenCalledWith('提交失败')
    })
  })

  describe('handleBack', () => {
    it('confirm 确认后调用 $router.back()', async () => {
      mountWithMocks(Promise.resolve())
      wrapper.vm.handleBack()
      await wrapper.vm.$nextTick()
      await Promise.resolve()
      await wrapper.vm.$nextTick()
      expect(mockConfirm).toHaveBeenCalledWith(
        '返回后当前填写的信息将丢失，确定返回吗？',
        '提示',
        expect.objectContaining({ type: 'warning' })
      )
      expect(mockRouter.back).toHaveBeenCalled()
    })

    it('用户取消confirm 不触发路由跳转', async () => {
      mountWithMocks(Promise.reject())
      wrapper.vm.handleBack()
      await wrapper.vm.$nextTick()
      await Promise.resolve().catch(() => {})
      await wrapper.vm.$nextTick()
      expect(mockRouter.back).not.toHaveBeenCalled()
    })
  })

  describe('nextStep step 0: receiver form validation callback', () => {
    it('validate returns invalid → warning message, step stays at 0', async () => {
      const localVue = createLocalVue()
      localVue.use(ElementUI)
      mockRouter = { back: jest.fn(), push: jest.fn() }
      mockMessage = buildMockMessage()
      const mockValidate = jest.fn((cb) => cb(false))
      wrapper = mount(CreatePage, {
        localVue,
        mocks: {
          $router: mockRouter,
          $message: mockMessage,
          $confirm: jest.fn(() => Promise.resolve())
        }
      })
      Object.defineProperty(wrapper.vm, '$refs', {
        value: { receiverForm: { validate: mockValidate } }
      })
      wrapper.setData({ activeStep: 0 })
      wrapper.vm.nextStep(0)
      expect(mockValidate).toHaveBeenCalled()
      expect(mockMessage.warning).toHaveBeenCalledWith('请完整填写收件人信息')
      expect(wrapper.vm.activeStep).toBe(0)
    })

    it('validate returns valid → activeStep increments to 1', async () => {
      const localVue = createLocalVue()
      localVue.use(ElementUI)
      mockMessage = buildMockMessage()
      const mockValidate = jest.fn((cb) => cb(true))
      wrapper = mount(CreatePage, {
        localVue,
        mocks: {
          $router: { back: jest.fn(), push: jest.fn() },
          $message: mockMessage,
          $confirm: jest.fn(() => Promise.resolve())
        }
      })
      Object.defineProperty(wrapper.vm, '$refs', {
        value: { receiverForm: { validate: mockValidate } }
      })
      wrapper.setData({ activeStep: 0 })
      wrapper.vm.nextStep(0)
      expect(wrapper.vm.activeStep).toBe(1)
    })
  })

  describe('nextStep step 2: declaredValue and calcTotalCost auto-populate', () => {
    it('sets declaredValue = totalProductAmount before advancing', () => {
      mountWithMocks(Promise.resolve())
      wrapper.setData({
        activeStep: 2,
        productItems: [
          { sku: 'A', name: 'P1', quantity: 2, price: 100, subtotal: 200, weight: 1 }
        ],
        shippingForm: {
          warehouseId: 1,
          shippingMethod: 'fedex_ground',
          declaredValue: 0,
          insuranceEnabled: true,
          insuranceFee: 0,
          handlingFee: 0,
          shippingFee: 0
        }
      })
      wrapper.vm.nextStep(2)
      expect(wrapper.vm.shippingForm.declaredValue).toBe(200)
      expect(wrapper.vm.shippingForm.insuranceFee).toBeCloseTo(4, 2)
      expect(wrapper.vm.activeStep).toBe(3)
    })
  })
})
