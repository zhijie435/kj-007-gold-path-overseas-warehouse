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
