<template>
  <div class="oversea-warehouse-list">
    <el-card class="page-card" shadow="never">
      <div class="table-toolbar">
        <div class="toolbar-left">
          <el-input
            v-model="keyword"
            placeholder="搜索仓库名称/WMS服务商"
            clearable
            style="width: 260px"
            @keyup.enter="fetchList"
          >
            <i slot="prefix" class="el-input__icon el-icon-search"></i>
          </el-input>
          <el-select
            v-model="statusFilter"
            clearable
            placeholder="状态筛选"
            style="width: 160px; margin-left: 12px"
          >
            <el-option label="启用" value="active" />
            <el-option label="测试中" value="testing" />
            <el-option label="异常" value="error" />
            <el-option label="禁用" value="disabled" />
          </el-select>
        </div>
        <div class="toolbar-right">
          <el-button type="primary" @click="handleAdd">
            <i class="el-icon-plus"></i> 新增配置
          </el-button>
        </div>
      </div>

      <el-table
        v-loading="loading"
        :data="tableData"
        border
        stripe
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="50" align="center" />
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column label="仓库信息" width="220">
          <template slot-scope="{ row }">
            <div class="warehouse-name">{{ row.warehouseName }}</div>
            <div class="muted text-sm">{{ row.warehouseCode }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="wmsProvider" label="WMS服务商" width="120" align="center" />
        <el-table-column prop="defaultShippingMethod" label="默认物流" width="140" />
        <el-table-column prop="handlingFee" label="操作费" width="100" align="right">
          <template slot-scope="{ row }">
            <span>¥{{ row.handlingFee }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="slaProcessingHours" label="处理时效SLA" width="110" align="center">
          <template slot-scope="{ row }">
            <span>{{ row.slaProcessingHours }}小时</span>
          </template>
        </el-table-column>
        <el-table-column label="自动推单" width="90" align="center">
          <template slot-scope="{ row }">
            <el-tag :type="row.autoPushEnabled ? 'success' : 'warning'" size="mini">
              {{ row.autoPushEnabled ? '已开启' : '未开启' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="自动同步库存" width="110" align="center">
          <template slot-scope="{ row }">
            <span v-if="row.autoSyncInventory">
              <i class="el-icon-check text-success"></i>
              <span class="muted text-sm"> ({{ row.inventorySyncIntervalMin }}分钟)</span>
            </span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template slot-scope="{ row }">
            <el-tag :type="getStatusTagType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="lastSyncAt" label="最近同步时间" width="160" />
        <el-table-column label="操作" width="360" fixed="right" align="center">
          <template slot-scope="{ row }">
            <el-button type="text" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" size="small" @click="handleToggleStatus(row)">
              {{ row.status === 'active' ? '禁用' : '启用' }}
            </el-button>
            <el-button
              type="text"
              size="small"
              :loading="testingId === row.id"
              @click="handleTestConnection(row)"
            >测试连接</el-button>
            <el-button type="text" size="small" @click="handleSyncInventory(row)">同步库存</el-button>
            <el-button type="text" size="small" @click="handleSyncTracking(row)">同步物流</el-button>
            <el-button type="text" size="small" style="color: #f56c6c" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        class="pagination"
        background
        layout="total, sizes, prev, pager, next, jumper"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        :page-size="pagination.pageSize"
        :current-page="pagination.currentPage"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
      />
    </el-card>

    <el-dialog
      :visible.sync="formDialogVisible"
      :title="isEdit ? '编辑海外仓配置' : '新增海外仓配置'"
      width="720px"
      append-to-body
      custom-class="warehouse-form-dialog"
      :close-on-click-modal="false"
    >
      <el-form
        ref="warehouseForm"
        :model="formData"
        :rules="formRules"
        label-width="140px"
        label-position="right"
      >
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="关联仓库" prop="warehouseId">
              <el-select v-model="formData.warehouseId" placeholder="请选择关联仓库" style="width: 100%">
                <el-option
                  v-for="item in warehouseOptions"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="WMS服务商" prop="wmsProvider">
              <el-select v-model="formData.wmsProvider" placeholder="请选择WMS服务商" style="width: 100%">
                <el-option label="ShipBob" value="shipbob" />
                <el-option label="ShipStation" value="shipstation" />
                <el-option label="Deliverr" value="deliverr" />
                <el-option label="Flexport" value="flexport" />
                <el-option label="易达云仓" value="yida" />
                <el-option label="谷仓海外仓" value="goodcang" />
                <el-option label="自定义" value="custom" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="API地址" prop="apiEndpoint">
              <el-input v-model="formData.apiEndpoint" placeholder="https://api.example.com" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="API Key" prop="apiKey">
              <el-input v-model="formData.apiKey" placeholder="请输入API Key" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="API Secret" prop="apiSecret">
              <el-input v-model="formData.apiSecret" type="password" show-password placeholder="请输入API Secret" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="仓库编码" prop="warehouseCode">
              <el-input v-model="formData.warehouseCode" placeholder="请输入仓库编码" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="默认物流渠道" prop="defaultShippingMethod">
              <el-select v-model="formData.defaultShippingMethod" placeholder="请选择默认物流" style="width: 100%">
                <el-option label="USPS Priority" value="usps_priority" />
                <el-option label="FedEx Ground" value="fedex_ground" />
                <el-option label="UPS Ground" value="ups_ground" />
                <el-option label="DHL Express" value="dhl_express" />
                <el-option label="Royal Mail" value="royal_mail" />
                <el-option label="Hermes" value="hermes" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="操作费(元)" prop="handlingFee">
              <el-input-number v-model="formData.handlingFee" :min="0" :precision="2" :step="0.5" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="仓储费(CBM/天)" prop="storageFeePerCbm">
              <el-input-number v-model="formData.storageFeePerCbm" :min="0" :precision="2" :step="0.5" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="处理SLA(小时)" prop="slaProcessingHours">
              <el-input-number v-model="formData.slaProcessingHours" :min="1" :step="1" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="自动推单">
              <el-switch v-model="formData.autoPushEnabled" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="自动同步库存">
              <el-switch v-model="formData.autoSyncInventory" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="库存同步间隔(分)" v-if="formData.autoSyncInventory">
              <el-input-number v-model="formData.inventorySyncIntervalMin" :min="5" :step="5" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="支持国家" prop="supportedCountries">
              <el-select
                v-model="formData.supportedCountries"
                multiple
                collapse-tags
                placeholder="请选择支持的国家（不选表示全部支持）"
                style="width: 100%"
              >
                <el-option label="美国(US)" value="US" />
                <el-option label="英国(GB)" value="GB" />
                <el-option label="德国(DE)" value="DE" />
                <el-option label="法国(FR)" value="FR" />
                <el-option label="日本(JP)" value="JP" />
                <el-option label="加拿大(CA)" value="CA" />
                <el-option label="澳大利亚(AU)" value="AU" />
                <el-option label="意大利(IT)" value="IT" />
                <el-option label="西班牙(ES)" value="ES" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="状态" prop="status">
              <el-radio-group v-model="formData.status">
                <el-radio label="active">启用</el-radio>
                <el-radio label="testing">测试</el-radio>
                <el-radio label="disabled">禁用</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div slot="footer">
        <el-button
          :loading="testLoading"
          @click="handleFormTest"
        >
          <i class="el-icon-connection"></i> 测试连接
        </el-button>
        <el-button @click="formDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">保存</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'OverseaWarehouseList',
  data() {
    return {
      loading: false,
      testLoading: false,
      submitLoading: false,
      testingId: null,
      keyword: '',
      statusFilter: '',
      selectedIds: [],
      formDialogVisible: false,
      isEdit: false,
      currentEditId: null,
      total: 0,
      pagination: {
        currentPage: 1,
        pageSize: 10
      },
      tableData: [],
      formData: {
        warehouseId: null,
        wmsProvider: '',
        apiEndpoint: '',
        apiKey: '',
        apiSecret: '',
        warehouseCode: '',
        defaultShippingMethod: '',
        handlingFee: 0,
        storageFeePerCbm: 0,
        slaProcessingHours: 24,
        autoPushEnabled: false,
        autoSyncInventory: false,
        inventorySyncIntervalMin: 30,
        supportedCountries: [],
        status: 'active'
      },
      formRules: {
        warehouseId: [{ required: true, message: '请选择关联仓库', trigger: 'change' }],
        wmsProvider: [{ required: true, message: '请选择WMS服务商', trigger: 'change' }],
        apiEndpoint: [{ required: true, message: '请输入API地址', trigger: 'blur' }],
        apiKey: [{ required: true, message: '请输入API Key', trigger: 'blur' }],
        warehouseCode: [{ required: true, message: '请输入仓库编码', trigger: 'blur' }],
        defaultShippingMethod: [{ required: true, message: '请选择默认物流渠道', trigger: 'change' }]
      },
      warehouseOptions: [
        { id: 1, name: '美国洛杉矶仓' },
        { id: 2, name: '美国纽约仓' },
        { id: 3, name: '英国伦敦仓' },
        { id: 4, name: '德国法兰克福仓' },
        { id: 5, name: '日本东京仓' },
        { id: 6, name: '加拿大多伦多仓' }
      ]
    }
  },
  computed: {},
  created() {
    this.fetchList()
  },
  methods: {
    fetchList() {
      this.loading = true
      setTimeout(() => {
        this.tableData = [
          {
            id: 1,
            warehouseId: 1,
            warehouseName: '美国洛杉矶仓',
            warehouseCode: 'US-LAX-01',
            wmsProvider: 'ShipBob',
            defaultShippingMethod: 'USPS Priority',
            handlingFee: 5.00,
            storageFeePerCbm: 3.50,
            slaProcessingHours: 24,
            autoPushEnabled: true,
            autoSyncInventory: true,
            inventorySyncIntervalMin: 30,
            supportedCountries: ['US', 'CA'],
            status: 'active',
            lastSyncAt: '2026-06-21 09:30:00'
          },
          {
            id: 2,
            warehouseId: 3,
            warehouseName: '英国伦敦仓',
            warehouseCode: 'GB-LHR-01',
            wmsProvider: 'Deliverr',
            defaultShippingMethod: 'Royal Mail',
            handlingFee: 4.50,
            storageFeePerCbm: 3.20,
            slaProcessingHours: 48,
            autoPushEnabled: true,
            autoSyncInventory: true,
            inventorySyncIntervalMin: 60,
            supportedCountries: ['GB', 'DE', 'FR'],
            status: 'active',
            lastSyncAt: '2026-06-21 08:00:00'
          },
          {
            id: 3,
            warehouseId: 5,
            warehouseName: '日本东京仓',
            warehouseCode: 'JP-NRT-01',
            wmsProvider: '谷仓海外仓',
            defaultShippingMethod: 'DHL Express',
            handlingFee: 6.00,
            storageFeePerCbm: 4.00,
            slaProcessingHours: 24,
            autoPushEnabled: false,
            autoSyncInventory: false,
            inventorySyncIntervalMin: 0,
            supportedCountries: ['JP'],
            status: 'testing',
            lastSyncAt: '2026-06-20 18:00:00'
          },
          {
            id: 4,
            warehouseId: 4,
            warehouseName: '德国法兰克福仓',
            warehouseCode: 'DE-FRA-01',
            wmsProvider: 'Flexport',
            defaultShippingMethod: 'Hermes',
            handlingFee: 5.50,
            storageFeePerCbm: 3.80,
            slaProcessingHours: 24,
            autoPushEnabled: true,
            autoSyncInventory: true,
            inventorySyncIntervalMin: 15,
            supportedCountries: ['DE', 'FR', 'IT', 'ES'],
            status: 'error',
            lastSyncAt: '2026-06-20 12:00:00'
          },
          {
            id: 5,
            warehouseId: 6,
            warehouseName: '加拿大多伦多仓',
            warehouseCode: 'CA-YTO-01',
            wmsProvider: 'ShipStation',
            defaultShippingMethod: 'UPS Ground',
            handlingFee: 5.20,
            storageFeePerCbm: 3.60,
            slaProcessingHours: 36,
            autoPushEnabled: true,
            autoSyncInventory: true,
            inventorySyncIntervalMin: 45,
            supportedCountries: ['CA', 'US'],
            status: 'active',
            lastSyncAt: '2026-06-21 09:00:00'
          }
        ]
        this.total = this.tableData.length
        this.loading = false
      }, 500)
    },
    getStatusTagType(status) {
      const map = {
        active: 'success',
        testing: 'warning',
        error: 'danger',
        disabled: 'info'
      }
      return map[status] || 'info'
    },
    getStatusLabel(status) {
      const map = {
        active: '启用',
        testing: '测试中',
        error: '异常',
        disabled: '禁用'
      }
      return map[status] || status
    },
    handleSelectionChange(selection) {
      this.selectedIds = selection.map(item => item.id)
    },
    handleSizeChange(size) {
      this.pagination.pageSize = size
      this.fetchList()
    },
    handleCurrentChange(page) {
      this.pagination.currentPage = page
      this.fetchList()
    },
    resetForm() {
      this.formData = {
        warehouseId: null,
        wmsProvider: '',
        apiEndpoint: '',
        apiKey: '',
        apiSecret: '',
        warehouseCode: '',
        defaultShippingMethod: '',
        handlingFee: 0,
        storageFeePerCbm: 0,
        slaProcessingHours: 24,
        autoPushEnabled: false,
        autoSyncInventory: false,
        inventorySyncIntervalMin: 30,
        supportedCountries: [],
        status: 'active'
      }
      this.$nextTick(() => {
        this.$refs.warehouseForm && this.$refs.warehouseForm.clearValidate()
      })
    },
    handleAdd() {
      this.isEdit = false
      this.currentEditId = null
      this.resetForm()
      this.formDialogVisible = true
    },
    handleEdit(row) {
      this.isEdit = true
      this.currentEditId = row.id
      this.formData = {
        warehouseId: row.warehouseId,
        wmsProvider: row.wmsProvider.toLowerCase().replace(/\s/g, '_'),
        apiEndpoint: 'https://api.' + row.wmsProvider.toLowerCase().replace(/\s/g, '') + '.com',
        apiKey: '****' + row.id,
        apiSecret: '****',
        warehouseCode: row.warehouseCode,
        defaultShippingMethod: row.defaultShippingMethod.toLowerCase().replace(/\s/g, '_'),
        handlingFee: row.handlingFee,
        storageFeePerCbm: row.storageFeePerCbm,
        slaProcessingHours: row.slaProcessingHours,
        autoPushEnabled: row.autoPushEnabled,
        autoSyncInventory: row.autoSyncInventory,
        inventorySyncIntervalMin: row.inventorySyncIntervalMin,
        supportedCountries: row.supportedCountries || [],
        status: row.status
      }
      this.$nextTick(() => {
        this.$refs.warehouseForm && this.$refs.warehouseForm.clearValidate()
      })
      this.formDialogVisible = true
    },
    handleSubmit() {
      this.$refs.warehouseForm.validate((valid) => {
        if (!valid) return
        this.submitLoading = true
        setTimeout(() => {
          this.$message.success(this.isEdit ? '编辑成功' : '新增成功')
          this.formDialogVisible = false
          this.submitLoading = false
          this.fetchList()
        }, 600)
      })
    },
    handleToggleStatus(row) {
      const nextStatus = row.status === 'active' ? 'disabled' : 'active'
      const action = nextStatus === 'active' ? '启用' : '禁用'
      this.$confirm(`确定要${action}仓库【${row.warehouseName}】吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        row.status = nextStatus
        this.$message.success(`${action}成功`)
      }).catch(() => {})
    },
    handleTestConnection(row) {
      this.testingId = row.id
      const startTime = Date.now()
      setTimeout(() => {
        const duration = Date.now() - startTime
        const isSuccess = Math.random() > 0.2
        this.testingId = null
        if (isSuccess) {
          this.$message.success(`连接成功！响应时间：${duration}ms`)
        } else {
          this.$message.error(`连接失败！错误：API密钥无效或网络超时 (${duration}ms)`)
        }
      }, 1500)
    },
    handleFormTest() {
      if (!this.formData.apiEndpoint || !this.formData.apiKey) {
        this.$message.warning('请先填写API地址和API Key')
        return
      }
      this.testLoading = true
      const startTime = Date.now()
      setTimeout(() => {
        const duration = Date.now() - startTime
        this.testLoading = false
        this.$message.success(`连接成功！响应时间：${duration}ms`)
      }, 1500)
    },
    handleSyncInventory(row) {
      this.$confirm(`确定要从【${row.warehouseName}】同步库存吗？此操作可能需要一些时间。`, '同步库存', {
        confirmButtonText: '开始同步',
        cancelButtonText: '取消',
        type: 'info'
      }).then(() => {
        const loading = this.$loading({
          lock: true,
          text: '正在同步库存...',
          spinner: 'el-icon-loading',
          background: 'rgba(0, 0, 0, 0.7)'
        })
        setTimeout(() => {
          loading.close()
          this.$message.success('库存同步完成，共更新 1,286 个SKU')
          row.lastSyncAt = this.$moment ? this.$moment().format('YYYY-MM-DD HH:mm:ss') : new Date().toLocaleString()
        }, 2000)
      }).catch(() => {})
    },
    handleSyncTracking(row) {
      this.$confirm(`确定要从【${row.warehouseName}】同步物流轨迹吗？`, '同步物流', {
        confirmButtonText: '开始同步',
        cancelButtonText: '取消',
        type: 'info'
      }).then(() => {
        const loading = this.$loading({
          lock: true,
          text: '正在同步物流轨迹...',
          spinner: 'el-icon-loading',
          background: 'rgba(0, 0, 0, 0.7)'
        })
        setTimeout(() => {
          loading.close()
          this.$message.success('物流轨迹同步完成，共更新 342 个运单')
        }, 1800)
      }).catch(() => {})
    },
    handleDelete(row) {
      this.$confirm(`确定要删除仓库配置【${row.warehouseName}】吗？删除后将无法恢复！`, '删除确认', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'error'
      }).then(() => {
        this.tableData = this.tableData.filter(item => item.id !== row.id)
        this.total = this.tableData.length
        this.$message.success('删除成功')
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.oversea-warehouse-list {
  padding: 16px;

  .page-card {
    .table-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .warehouse-name {
      font-weight: 500;
      color: #303133;
    }

    .muted {
      color: #909399;
    }

    .text-sm {
      font-size: 12px;
    }

    .text-success {
      color: #67c23a;
    }

    .pagination {
      margin-top: 16px;
      text-align: right;
    }
  }

  .warehouse-form-dialog {
    ::v-deep .el-form-item {
      margin-bottom: 18px;
    }
  }
}
</style>
