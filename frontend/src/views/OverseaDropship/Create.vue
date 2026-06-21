<template>
  <div class="oversea-dropship-create">
    <el-card class="page-card" shadow="never">
      <div slot="header" class="card-header">
        <div class="header-left">
          <el-button type="text" icon="el-icon-back" @click="handleBack">返回列表</el-button>
          <el-divider direction="vertical" />
          <span class="page-title">创建一件代发单</span>
        </div>
        <div class="header-right">
          <el-tag type="info" effect="plain" v-if="generatedNo">
            单号：{{ generatedNo }}
          </el-tag>
        </div>
      </div>

      <el-steps
        :active="activeStep"
        finish-status="success"
        align-center
        class="create-steps"
      >
        <el-step title="收件信息" @click.native="goToStep(0)" />
        <el-step title="商品明细" @click.native="goToStep(1)" />
        <el-step title="物流与费用" @click.native="goToStep(2)" />
        <el-step title="确认提交" @click.native="goToStep(3)" />
      </el-steps>

      <div class="step-content">
        <div v-show="activeStep === 0" class="step-panel">
          <div class="panel-title">
            <i class="el-icon-user"></i>
            <span>收件人信息</span>
          </div>
          <el-form
            ref="receiverForm"
            :model="receiverForm"
            :rules="receiverRules"
            label-width="110px"
            label-position="right"
          >
            <el-row :gutter="24">
              <el-col :span="12">
                <el-form-item label="收件人姓名" prop="name">
                  <el-input v-model="receiverForm.name" placeholder="请输入收件人姓名" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="联系电话" prop="phone">
                  <el-input v-model="receiverForm.phone" placeholder="请输入联系电话" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="24">
              <el-col :span="12">
                <el-form-item label="电子邮箱">
                  <el-input v-model="receiverForm.email" placeholder="选填，用于物流通知" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="国家" prop="country">
                  <el-select
                    v-model="receiverForm.country"
                    placeholder="请选择国家"
                    filterable
                    style="width: 100%"
                    @change="handleCountryChange"
                  >
                    <el-option
                      v-for="item in countryOptions"
                      :key="item.code"
                      :label="`${item.name} (${item.code})`"
                      :value="item.code"
                    >
                      <span>{{ item.flag }} {{ item.name }}</span>
                      <span style="float: right; color: #8492a6; font-size: 13px">{{ item.code }}</span>
                    </el-option>
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="24">
              <el-col :span="8">
                <el-form-item label="省/州" prop="state">
                  <el-input v-model="receiverForm.state" placeholder="省/州" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="城市" prop="city">
                  <el-input v-model="receiverForm.city" placeholder="城市" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="邮编" prop="postalCode">
                  <el-input v-model="receiverForm.postalCode" placeholder="邮政编码" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="24">
              <el-col :span="24">
                <el-form-item label="详细地址" prop="address">
                  <el-input
                    v-model="receiverForm.address"
                    type="textarea"
                    :rows="2"
                    placeholder="街道、门牌号等详细地址"
                  />
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>
        </div>

        <div v-show="activeStep === 1" class="step-panel">
          <div class="panel-title">
            <i class="el-icon-goods"></i>
            <span>商品明细</span>
            <div class="panel-actions">
              <el-input
                v-model="skuSearch"
                placeholder="搜索SKU添加商品"
                style="width: 280px"
                size="small"
                clearable
              >
                <i slot="prefix" class="el-input__icon el-icon-search"></i>
              </el-input>
              <el-button
                type="primary"
                size="small"
                icon="el-icon-plus"
                @click="addItemRow"
              >
                添加商品
              </el-button>
            </div>
          </div>

          <el-table
            :data="productItems"
            border
            class="product-table"
          >
            <el-table-column type="index" label="序号" width="60" align="center" />
            <el-table-column label="SKU" width="160">
              <template slot-scope="{ row, $index }">
                <el-input
                  v-model="row.sku"
                  size="small"
                  placeholder="输入或选择SKU"
                  @blur="calcItemSubtotal($index)"
                />
              </template>
            </el-table-column>
            <el-table-column label="商品名称" min-width="180">
              <template slot-scope="{ row }">
                <el-input v-model="row.name" size="small" placeholder="商品名称" />
              </template>
            </el-table-column>
            <el-table-column label="规格" width="140">
              <template slot-scope="{ row }">
                <el-input v-model="row.spec" size="small" placeholder="规格/颜色" />
              </template>
            </el-table-column>
            <el-table-column label="数量" width="100" align="center">
              <template slot-scope="{ row, $index }">
                <el-input-number
                  v-model="row.quantity"
                  :min="1"
                  :step="1"
                  size="small"
                  controls-position="right"
                  @change="calcItemSubtotal($index)"
                />
              </template>
            </el-table-column>
            <el-table-column label="单价(USD)" width="130" align="right">
              <template slot-scope="{ row, $index }">
                <el-input-number
                  v-model="row.price"
                  :min="0"
                  :step="0.5"
                  :precision="2"
                  size="small"
                  controls-position="right"
                  @change="calcItemSubtotal($index)"
                />
              </template>
            </el-table-column>
            <el-table-column label="小计" width="110" align="right">
              <template slot-scope="{ row }">
                <span class="subtotal-text">{{ row.subtotal ? '$' + row.subtotal.toFixed(2) : '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="重量(kg)" width="110" align="right">
              <template slot-scope="{ row }">
                <el-input-number
                  v-model="row.weight"
                  :min="0"
                  :step="0.1"
                  :precision="3"
                  size="small"
                  controls-position="right"
                />
              </template>
            </el-table-column>
            <el-table-column label="HS编码" width="130">
              <template slot-scope="{ row }">
                <el-input v-model="row.hsCode" size="small" placeholder="海关编码" />
              </template>
            </el-table-column>
            <el-table-column label="批次号" width="120">
              <template slot-scope="{ row }">
                <el-input v-model="row.batchNo" size="small" placeholder="批次号" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="70" align="center" fixed="right">
              <template slot-scope="{ $index }">
                <el-button
                  type="text"
                  size="small"
                  style="color: #f56c6c"
                  icon="el-icon-delete"
                  @click="removeItemRow($index)"
                  v-if="productItems.length > 1"
                />
              </template>
            </el-table-column>
          </el-table>

          <div class="product-summary">
            <div class="summary-item">
              <span class="label">商品行数：</span>
              <span class="value">{{ productItems.length }}</span>
            </div>
            <div class="summary-item">
              <span class="label">总件数：</span>
              <span class="value highlight">{{ totalQuantity }} 件</span>
            </div>
            <div class="summary-item">
              <span class="label">总重量：</span>
              <span class="value">{{ totalWeight.toFixed(3) }} kg</span>
            </div>
            <div class="summary-item">
              <span class="label">商品总额：</span>
              <span class="value amount">${{ totalProductAmount.toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <div v-show="activeStep === 2" class="step-panel">
          <div class="panel-title">
            <i class="el-icon-truck"></i>
            <span>物流与费用</span>
          </div>

          <el-form label-width="140px" label-position="right">
            <el-row :gutter="24">
              <el-col :span="12">
                <el-form-item label="发货海外仓" required>
                  <el-select
                    v-model="shippingForm.warehouseId"
                    placeholder="请选择海外仓"
                    style="width: 100%"
                  >
                    <el-option
                      v-for="item in recommendedWarehouses"
                      :key="item.id"
                      :value="item.id"
                    >
                      <span>{{ item.name }}</span>
                      <el-tag
                        v-if="item.recommended"
                        type="success"
                        size="mini"
                        style="margin-left: 8px"
                      >推荐</el-tag>
                    </el-option>
                  </el-select>
                  <div class="muted text-xs mt-4" v-if="recommendedWarehouses.length">
                    <i class="el-icon-info"></i>
                    系统根据收货国家【{{ receiverForm.country }}】推荐最优仓库
                  </div>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="物流渠道" required>
                  <el-select
                    v-model="shippingForm.shippingMethod"
                    placeholder="请选择物流渠道"
                    style="width: 100%"
                  >
                    <el-option label="USPS Priority Mail (5-7天)" value="usps_priority" />
                    <el-option label="FedEx Ground (3-5天)" value="fedex_ground" />
                    <el-option label="UPS Expedited (2-3天)" value="ups_expedited" />
                    <el-option label="DHL Express (1-2天)" value="dhl_express" />
                    <el-option label="Royal Mail Tracked (3-5天)" value="royal_mail" />
                    <el-option label="Hermes Standard (5-7天)" value="hermes_std" />
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>

            <el-divider content-position="left">费用明细</el-divider>

            <el-row :gutter="24">
              <el-col :span="8">
                <el-form-item label="保险费开关">
                  <el-switch
                    v-model="shippingForm.insuranceEnabled"
                    active-text="开启"
                    inactive-text="关闭"
                    @change="calcTotalCost"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="申报价值(USD)">
                  <el-input-number
                    v-model="shippingForm.declaredValue"
                    :min="0"
                    :step="1"
                    :precision="2"
                    style="width: 100%"
                    @change="calcTotalCost"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="币种">
                  <el-select v-model="shippingForm.currency" style="width: 100%">
                    <el-option label="美元 - USD" value="USD" />
                    <el-option label="欧元 - EUR" value="EUR" />
                    <el-option label="英镑 - GBP" value="GBP" />
                    <el-option label="日元 - JPY" value="JPY" />
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="24">
              <el-col :span="8">
                <el-form-item label="操作费(USD)">
                  <el-input-number
                    v-model="shippingForm.handlingFee"
                    :min="0"
                    :precision="2"
                    :step="0.5"
                    style="width: 100%"
                    disabled
                  />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="运费(USD)">
                  <el-input-number
                    v-model="shippingForm.shippingFee"
                    :min="0"
                    :precision="2"
                    :step="0.5"
                    style="width: 100%"
                    @change="calcTotalCost"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="保险费(USD)">
                  <el-input-number
                    v-model="shippingForm.insuranceFee"
                    :min="0"
                    :precision="2"
                    :step="0.5"
                    style="width: 100%"
                    :disabled="!shippingForm.insuranceEnabled"
                    @change="calcTotalCost"
                  />
                </el-form-item>
              </el-col>
            </el-row>

            <el-card class="cost-summary-card" shadow="never">
              <el-row :gutter="24">
                <el-col :span="6">
                  <div class="cost-row">
                    <div class="cost-label">商品金额</div>
                    <div class="cost-value">${{ totalProductAmount.toFixed(2) }}</div>
                  </div>
                </el-col>
                <el-col :span="6">
                  <div class="cost-row">
                    <div class="cost-label">操作费</div>
                    <div class="cost-value">${{ shippingForm.handlingFee.toFixed(2) }}</div>
                  </div>
                </el-col>
                <el-col :span="6">
                  <div class="cost-row">
                    <div class="cost-label">运费</div>
                    <div class="cost-value">${{ shippingForm.shippingFee.toFixed(2) }}</div>
                  </div>
                </el-col>
                <el-col :span="6">
                  <div class="cost-row total">
                    <div class="cost-label">订单总费用</div>
                    <div class="cost-value">${{ totalCost.toFixed(2) }}</div>
                  </div>
                </el-col>
              </el-row>
            </el-card>
          </el-form>
        </div>

        <div v-show="activeStep === 3" class="step-panel">
          <div class="panel-title">
            <i class="el-icon-document-checked"></i>
            <span>确认提交</span>
          </div>

          <el-row :gutter="24">
            <el-col :span="14">
              <el-descriptions title="收件人信息" :column="2" border size="small">
                <el-descriptions-item label="收件人">{{ receiverForm.name }}</el-descriptions-item>
                <el-descriptions-item label="电话">{{ receiverForm.phone }}</el-descriptions-item>
                <el-descriptions-item label="邮箱">{{ receiverForm.email || '-' }}</el-descriptions-item>
                <el-descriptions-item label="国家">{{ getCountryName(receiverForm.country) }}</el-descriptions-item>
                <el-descriptions-item label="省/州">{{ receiverForm.state }}</el-descriptions-item>
                <el-descriptions-item label="城市">{{ receiverForm.city }}</el-descriptions-item>
                <el-descriptions-item label="邮编">{{ receiverForm.postalCode }}</el-descriptions-item>
                <el-descriptions-item :span="2" label="详细地址">
                  {{ receiverForm.address }}
                </el-descriptions-item>
              </el-descriptions>

              <el-descriptions title="物流信息" :column="2" border size="small" style="margin-top: 16px">
                <el-descriptions-item label="海外仓">{{ getWarehouseName(shippingForm.warehouseId) }}</el-descriptions-item>
                <el-descriptions-item label="物流渠道">{{ getShippingName(shippingForm.shippingMethod) }}</el-descriptions-item>
                <el-descriptions-item label="币种">{{ shippingForm.currency }}</el-descriptions-item>
                <el-descriptions-item label="申报价值">${{ shippingForm.declaredValue.toFixed(2) }}</el-descriptions-item>
              </el-descriptions>
            </el-col>

            <el-col :span="10">
              <el-descriptions title="商品统计" :column="1" border size="small">
                <el-descriptions-item label="商品行数">{{ productItems.length }} 行</el-descriptions-item>
                <el-descriptions-item label="总件数">{{ totalQuantity }} 件</el-descriptions-item>
                <el-descriptions-item label="总重量">{{ totalWeight.toFixed(3) }} kg</el-descriptions-item>
              </el-descriptions>

              <el-card class="fee-breakdown" shadow="never" style="margin-top: 16px">
                <div slot="header" class="fee-header">
                  <span>费用明细</span>
                  <span class="currency-tag">{{ shippingForm.currency }}</span>
                </div>
                <div class="fee-row">
                  <span>商品金额</span>
                  <span>${{ totalProductAmount.toFixed(2) }}</span>
                </div>
                <div class="fee-row">
                  <span>操作费</span>
                  <span>${{ shippingForm.handlingFee.toFixed(2) }}</span>
                </div>
                <div class="fee-row">
                  <span>运费</span>
                  <span>${{ shippingForm.shippingFee.toFixed(2) }}</span>
                </div>
                <div class="fee-row" v-if="shippingForm.insuranceEnabled">
                  <span>保险费</span>
                  <span>${{ shippingForm.insuranceFee.toFixed(2) }}</span>
                </div>
                <div class="fee-row total">
                  <span>合计</span>
                  <span>${{ totalCost.toFixed(2) }}</span>
                </div>
              </el-card>
            </el-col>
          </el-row>

          <el-table
            :data="productItems"
            border
            size="small"
            style="margin-top: 16px"
          >
            <el-table-column type="index" label="#" width="50" align="center" />
            <el-table-column prop="sku" label="SKU" width="130" />
            <el-table-column prop="name" label="商品名称" />
            <el-table-column prop="spec" label="规格" width="130" />
            <el-table-column prop="quantity" label="数量" width="80" align="center" />
            <el-table-column prop="price" label="单价" width="100" align="right">
              <template slot-scope="{ row }">${{ row.price ? row.price.toFixed(2) : '-' }}</template>
            </el-table-column>
            <el-table-column label="小计" width="110" align="right">
              <template slot-scope="{ row }">${{ row.subtotal ? row.subtotal.toFixed(2) : '-' }}</template>
            </el-table-column>
            <el-table-column prop="weight" label="重量(kg)" width="100" align="right" />
            <el-table-column prop="hsCode" label="HS编码" width="120" />
          </el-table>

          <el-form label-width="100px" style="margin-top: 24px">
            <el-form-item label="订单备注">
              <el-input
                v-model="submitRemark"
                type="textarea"
                :rows="3"
                placeholder="选填：内部处理备注信息"
                maxlength="500"
                show-word-limit
              />
            </el-form-item>
          </el-form>
        </div>
      </div>

      <div class="step-footer">
        <div v-if="activeStep === 0">
          <el-button @click="handleBack">取消</el-button>
          <el-button type="primary" @click="nextStep(0)">下一步</el-button>
        </div>
        <div v-else-if="activeStep < 3">
          <el-button @click="prevStep">上一步</el-button>
          <el-button type="primary" @click="nextStep(activeStep)">下一步</el-button>
        </div>
        <div v-else>
          <el-button @click="prevStep">上一步</el-button>
          <el-button type="info" plain @click="goToStep(0)">重新编辑</el-button>
          <el-button type="success" :loading="submitLoading" icon="el-icon-check" @click="handleSubmit">
            提交创建
          </el-button>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'OverseaDropshipCreate',
  data() {
    return {
      activeStep: 0,
      generatedNo: '',
      submitLoading: false,
      submitRemark: '',
      skuSearch: '',
      receiverForm: {
        name: '',
        phone: '',
        email: '',
        country: '',
        state: '',
        city: '',
        postalCode: '',
        address: ''
      },
      receiverRules: {
        name: [{ required: true, message: '请输入收件人姓名', trigger: 'blur' }],
        phone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }],
        country: [{ required: true, message: '请选择国家', trigger: 'change' }],
        state: [{ required: true, message: '请输入省/州', trigger: 'blur' }],
        city: [{ required: true, message: '请输入城市', trigger: 'blur' }],
        postalCode: [{ required: true, message: '请输入邮编', trigger: 'blur' }],
        address: [{ required: true, message: '请输入详细地址', trigger: 'blur' }]
      },
      productItems: [
        { sku: '', name: '', spec: '', quantity: 1, price: 0, subtotal: 0, weight: 0.1, hsCode: '', batchNo: '' }
      ],
      shippingForm: {
        warehouseId: null,
        shippingMethod: '',
        insuranceEnabled: false,
        declaredValue: 0,
        currency: 'USD',
        handlingFee: 5.00,
        shippingFee: 12.50,
        insuranceFee: 0
      },
      countryOptions: [
        { code: 'US', name: '美国', flag: '🇺🇸' },
        { code: 'GB', name: '英国', flag: '🇬🇧' },
        { code: 'DE', name: '德国', flag: '🇩🇪' },
        { code: 'FR', name: '法国', flag: '🇫🇷' },
        { code: 'JP', name: '日本', flag: '🇯🇵' },
        { code: 'CA', name: '加拿大', flag: '🇨🇦' },
        { code: 'AU', name: '澳大利亚', flag: '🇦🇺' },
        { code: 'IT', name: '意大利', flag: '🇮🇹' },
        { code: 'ES', name: '西班牙', flag: '🇪🇸' }
      ],
      warehouseList: [
        { id: 1, name: '美国洛杉矶仓', countries: ['US', 'CA'] },
        { id: 2, name: '美国纽约仓', countries: ['US', 'CA'] },
        { id: 3, name: '英国伦敦仓', countries: ['GB', 'IE'] },
        { id: 4, name: '德国法兰克福仓', countries: ['DE', 'FR', 'IT', 'ES', 'NL', 'BE'] },
        { id: 5, name: '日本东京仓', countries: ['JP'] }
      ]
    }
  },
  computed: {
    recommendedWarehouses() {
      const country = this.receiverForm.country
      return this.warehouseList.map(w => ({
        ...w,
        recommended: country ? w.countries.includes(country) : false
      })).sort((a, b) => (b.recommended ? 1 : 0) - (a.recommended ? 1 : 0))
    },
    totalQuantity() {
      return this.productItems.reduce((sum, item) => sum + (item.quantity || 0), 0)
    },
    totalWeight() {
      return this.productItems.reduce((sum, item) => sum + (item.weight || 0) * (item.quantity || 0), 0)
    },
    totalProductAmount() {
      return this.productItems.reduce((sum, item) => sum + (item.subtotal || 0), 0)
    },
    totalCost() {
      let total = this.totalProductAmount + this.shippingForm.handlingFee + this.shippingForm.shippingFee
      if (this.shippingForm.insuranceEnabled) {
        total += this.shippingForm.insuranceFee
      }
      return total
    }
  },
  created() {
    this.generatedNo = 'DS' + this.formatDate(new Date()) + Math.floor(Math.random() * 10000).toString().padStart(4, '0')
  },
  methods: {
    formatDate(date) {
      const y = date.getFullYear()
      const m = (date.getMonth() + 1).toString().padStart(2, '0')
      const d = date.getDate().toString().padStart(2, '0')
      const h = date.getHours().toString().padStart(2, '0')
      const min = date.getMinutes().toString().padStart(2, '0')
      const s = date.getSeconds().toString().padStart(2, '0')
      return y + m + d + h + min + s
    },
    goToStep(step) {
      this.activeStep = step
    },
    prevStep() {
      if (this.activeStep > 0) {
        this.activeStep--
      }
    },
    nextStep(currentStep) {
      if (currentStep === 0) {
        this.$refs.receiverForm.validate((valid) => {
          if (valid) {
            this.activeStep++
          } else {
            this.$message.warning('请完整填写收件人信息')
          }
        })
      } else if (currentStep === 1) {
        if (this.productItems.length === 0) {
          this.$message.warning('请至少添加一件商品')
          return
        }
        const invalid = this.productItems.find(item => !item.sku || !item.name || item.quantity <= 0)
        if (invalid) {
          this.$message.warning('请完整填写商品信息（SKU、名称、数量必填）')
          return
        }
        this.activeStep++
      } else if (currentStep === 2) {
        if (!this.shippingForm.warehouseId) {
          this.$message.warning('请选择发货海外仓')
          return
        }
        if (!this.shippingForm.shippingMethod) {
          this.$message.warning('请选择物流渠道')
          return
        }
        this.shippingForm.declaredValue = this.totalProductAmount
        this.calcTotalCost()
        this.activeStep++
      }
    },
    handleCountryChange() {
      const recommended = this.recommendedWarehouses.find(w => w.recommended)
      if (recommended) {
        this.shippingForm.warehouseId = recommended.id
      }
    },
    addItemRow() {
      this.productItems.push({
        sku: this.skuSearch || '',
        name: '',
        spec: '',
        quantity: 1,
        price: 0,
        subtotal: 0,
        weight: 0.1,
        hsCode: '',
        batchNo: ''
      })
      this.skuSearch = ''
    },
    removeItemRow(index) {
      this.productItems.splice(index, 1)
    },
    calcItemSubtotal(index) {
      const item = this.productItems[index]
      item.subtotal = Math.round((item.price || 0) * (item.quantity || 0) * 100) / 100
    },
    calcTotalCost() {
      if (this.shippingForm.insuranceEnabled && !this.shippingForm.insuranceFee) {
        this.shippingForm.insuranceFee = Math.round(this.totalProductAmount * 0.02 * 100) / 100
      }
    },
    getCountryName(code) {
      const found = this.countryOptions.find(c => c.code === code)
      return found ? `${found.flag} ${found.name}` : code
    },
    getWarehouseName(id) {
      const found = this.warehouseList.find(w => w.id === id)
      return found ? found.name : '-'
    },
    getShippingName(value) {
      const map = {
        usps_priority: 'USPS Priority Mail',
        fedex_ground: 'FedEx Ground',
        ups_expedited: 'UPS Expedited',
        dhl_express: 'DHL Express',
        royal_mail: 'Royal Mail Tracked',
        hermes_std: 'Hermes Standard'
      }
      return map[value] || value
    },
    handleBack() {
      this.$confirm('返回后当前填写的信息将丢失，确定返回吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.$router.back()
      }).catch(() => {})
    },
    handleSubmit() {
      this.$confirm('确认创建此代发单吗？提交后将进入审核流程。', '提交确认', {
        confirmButtonText: '确认创建',
        cancelButtonText: '返回修改',
        type: 'success'
      }).then(() => {
        this.submitLoading = true
        setTimeout(() => {
          this.submitLoading = false
          this.$message.success(`代发单【${this.generatedNo}】创建成功，已提交审核`)
          this.$router.push({ path: '/oversea-dropship' })
        }, 1000)
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.oversea-dropship-create {
  padding: 16px;

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .header-left {
      display: flex;
      align-items: center;
    }

    .page-title {
      font-size: 16px;
      font-weight: 500;
      color: #303133;
    }
  }

  .create-steps {
    padding: 24px 40px 32px;
    background: #fafafa;
    border-radius: 8px;
    margin-bottom: 24px;
  }

  .step-panel {
    min-height: 400px;
    padding: 0 8px;
  }

  .panel-title {
    display: flex;
    align-items: center;
    font-size: 15px;
    font-weight: 500;
    color: #303133;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #ebeef5;

    i {
      margin-right: 8px;
      font-size: 18px;
      color: #409EFF;
    }

    .panel-actions {
      margin-left: auto;
      display: flex;
      gap: 12px;
      align-items: center;
    }
  }

  .product-table {
    ::v-deep .el-input,
    ::v-deep .el-input-number {
      width: 100%;
    }
  }

  .subtotal-text {
    color: #67C23A;
    font-weight: 500;
  }

  .product-summary {
    display: flex;
    gap: 32px;
    justify-content: flex-end;
    padding: 16px 24px;
    margin-top: 16px;
    background: #fafafa;
    border-radius: 6px;

    .summary-item {
      display: flex;
      align-items: center;

      .label {
        color: #606266;
        margin-right: 8px;
      }

      .value {
        font-weight: 500;
        color: #303133;

        &.highlight {
          color: #409EFF;
        }

        &.amount {
          color: #f56c6c;
          font-size: 16px;
        }
      }
    }
  }

  .cost-summary-card {
    background: #f5f7fa;
    border: 1px dashed #dcdfe6;

    .cost-row {
      text-align: center;

      .cost-label {
        font-size: 13px;
        color: #606266;
        margin-bottom: 6px;
      }

      .cost-value {
        font-size: 16px;
        color: #303133;
        font-weight: 500;
      }

      &.total {
        .cost-label {
          color: #303133;
          font-weight: 500;
        }

        .cost-value {
          font-size: 22px;
          color: #f56c6c;
          font-weight: 600;
        }
      }
    }
  }

  .fee-breakdown {
    ::v-deep .el-card__body {
      padding: 12px 16px;
    }

    .fee-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: 500;
    }

    .currency-tag {
      font-size: 12px;
      color: #909399;
    }

    .fee-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px dashed #ebeef5;
      color: #606266;
      font-size: 14px;

      &:last-child {
        border-bottom: none;
      }

      &.total {
        font-weight: 600;
        color: #303133;
        padding-top: 12px;
        border-top: 2px solid #ebeef5;
        border-bottom: none;
        margin-top: 4px;
        font-size: 15px;

        span:last-child {
          color: #f56c6c;
          font-size: 18px;
        }
      }
    }
  }

  .step-footer {
    margin-top: 32px;
    padding-top: 20px;
    border-top: 1px solid #ebeef5;
    text-align: center;

    .el-button + .el-button {
      margin-left: 16px;
    }
  }

  .muted {
    color: #909399;
  }

  .text-xs {
    font-size: 12px;
  }

  .mt-4 {
    margin-top: 4px;
  }
}
</style>
