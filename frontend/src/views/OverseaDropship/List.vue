<template>
  <div class="oversea-dropship-list">
    <el-row :gutter="16" class="stat-cards">
      <el-col :xs="12" :sm="8" :md="8" :lg="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon pending-review">
              <i class="el-icon-edit-outline"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.pendingReview }}</div>
              <div class="stat-label">待审核</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon pending-push">
              <i class="el-icon-upload2"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.pendingPush }}</div>
              <div class="stat-label">待推单</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon in-transit">
              <i class="el-icon-truck"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.inTransit }}</div>
              <div class="stat-label">运输中</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon exception">
              <i class="el-icon-warning-outline"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.exception }}</div>
              <div class="stat-label">异常</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon today-new">
              <i class="el-icon-document-add"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.todayNew }}</div>
              <div class="stat-label">今日新增</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="8" :lg="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon complete-rate">
              <i class="el-icon-data-line"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.completeRate }}%</div>
              <div class="stat-label">完成率</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="filter-card" shadow="never">
      <el-form :model="filterForm" inline label-width="90px" label-position="right">
        <el-form-item label="关键词">
          <el-input
            v-model="filterForm.keyword"
            placeholder="单号/外部单号/运单号/收件人"
            clearable
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select
            v-model="filterForm.status"
            multiple
            collapse-tags
            clearable
            placeholder="请选择状态"
            style="width: 240px"
          >
            <el-option
              v-for="item in statusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="海外仓">
          <el-select
            v-model="filterForm.warehouseId"
            clearable
            placeholder="请选择海外仓"
            style="width: 180px"
          >
            <el-option
              v-for="item in warehouseOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="来源渠道">
          <el-select
            v-model="filterForm.channel"
            clearable
            placeholder="请选择渠道"
            style="width: 180px"
          >
            <el-option
              v-for="item in channelOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="国家">
          <el-select
            v-model="filterForm.country"
            clearable
            placeholder="请选择国家"
            style="width: 180px"
          >
            <el-option
              v-for="item in countryOptions"
              :key="item.code"
              :label="item.name"
              :value="item.code"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="filterForm.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            style="width: 260px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <i class="el-icon-search"></i> 搜索
          </el-button>
          <el-button @click="handleReset">
            <i class="el-icon-refresh"></i> 重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <div class="table-toolbar">
        <div class="toolbar-left">
          <el-button
            type="success"
            :disabled="selectedIds.length === 0"
            @click="handleBatchReview"
          >
            <i class="el-icon-check"></i> 批量审核
          </el-button>
          <el-button
            type="warning"
            :disabled="selectedIds.length === 0"
            @click="handleBatchPush"
          >
            <i class="el-icon-upload"></i> 批量推送
          </el-button>
        </div>
        <div class="toolbar-right">
          <el-button type="primary" @click="handleCreate">
            <i class="el-icon-plus"></i> 创建代发单
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
        <el-table-column prop="dropshipNo" label="代发单号" width="180" show-overflow-tooltip>
          <template slot-scope="{ row }">
            <el-link type="primary" @click="handleView(row)">{{ row.dropshipNo }}</el-link>
          </template>
        </el-table-column>
        <el-table-column prop="externalOrderNo" label="外部单号" width="160" show-overflow-tooltip />
        <el-table-column prop="sourceChannel" label="来源渠道" width="100" align="center" />
        <el-table-column prop="warehouseName" label="海外仓" width="120" />
        <el-table-column label="收件人" width="160">
          <template slot-scope="{ row }">
            <div>{{ row.receiverName }}</div>
            <div class="muted">{{ row.receiverPhone }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="receiverCountry" label="国家" width="80" align="center" />
        <el-table-column prop="totalItems" label="商品件数" width="90" align="center" />
        <el-table-column prop="totalCost" label="费用" width="100" align="right">
          <template slot-scope="{ row }">
            <span class="amount">{{ row.currency }} {{ row.totalCost }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template slot-scope="{ row }">
            <el-tag :type="getStatusColor(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="trackingNo" label="运单号" width="160" show-overflow-tooltip>
          <template slot-scope="{ row }">
            <span v-if="row.trackingNo">{{ row.trackingNo }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="160" />
        <el-table-column label="操作" width="260" fixed="right" align="center">
          <template slot-scope="{ row }">
            <el-button type="text" size="small" @click="handleView(row)">查看</el-button>
            <el-button
              type="text"
              size="small"
              :disabled="!canReview(row.status)"
              @click="handleReview(row)"
            >审核</el-button>
            <el-button
              type="text"
              size="small"
              :disabled="!canPush(row.status)"
              @click="handlePush(row)"
            >推送</el-button>
            <el-button
              type="text"
              size="small"
              :disabled="!canCancel(row.status)"
              @click="handleCancel(row)"
            >取消</el-button>
            <el-button
              type="text"
              size="small"
              :disabled="!canRetry(row.status)"
              @click="handleRetry(row)"
            >重试</el-button>
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
      :visible.sync="detailDialogVisible"
      title="代发单详情"
      width="900px"
      append-to-body
      custom-class="detail-dialog"
    >
      <div v-if="currentDetail">
        <el-descriptions title="基础信息" :column="3" border size="small">
          <el-descriptions-item label="代发单号">{{ currentDetail.dropshipNo }}</el-descriptions-item>
          <el-descriptions-item label="外部单号">{{ currentDetail.externalOrderNo || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusColor(currentDetail.status)" size="small">
              {{ getStatusLabel(currentDetail.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="来源渠道">{{ currentDetail.sourceChannel }}</el-descriptions-item>
          <el-descriptions-item label="海外仓">{{ currentDetail.warehouseName }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ currentDetail.createdAt }}</el-descriptions-item>
          <el-descriptions-item label="物流渠道">{{ currentDetail.shippingMethodCode || '-' }}</el-descriptions-item>
          <el-descriptions-item label="运单号">{{ currentDetail.trackingNo || '-' }}</el-descriptions-item>
          <el-descriptions-item label="总费用">{{ currentDetail.currency }} {{ currentDetail.totalCost }}</el-descriptions-item>
        </el-descriptions>

        <el-descriptions title="收件信息" :column="2" border size="small" style="margin-top: 20px">
          <el-descriptions-item label="收件人">{{ currentDetail.receiverName }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ currentDetail.receiverPhone }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ currentDetail.receiverEmail || '-' }}</el-descriptions-item>
          <el-descriptions-item label="国家">{{ currentDetail.receiverCountry }}</el-descriptions-item>
          <el-descriptions-item :span="2" label="详细地址">
            {{ currentDetail.receiverAddress }}, {{ currentDetail.receiverCity }} {{ currentDetail.receiverState }} {{ currentDetail.receiverPostalCode }}
          </el-descriptions-item>
        </el-descriptions>

        <div style="margin-top: 20px">
          <h4 class="section-title">商品明细</h4>
          <el-table :data="currentDetail.items" border size="small">
            <el-table-column prop="sku" label="SKU" width="140" />
            <el-table-column prop="name" label="商品名称" />
            <el-table-column prop="spec" label="规格" width="120" />
            <el-table-column prop="quantity" label="数量" width="80" align="center" />
            <el-table-column prop="price" label="单价" width="100" align="right" />
            <el-table-column prop="subtotal" label="小计" width="100" align="right" />
            <el-table-column prop="weight" label="重量(kg)" width="100" align="right" />
            <el-table-column prop="hsCode" label="HS编码" width="120" />
          </el-table>
        </div>

        <el-row :gutter="20" style="margin-top: 20px">
          <el-col :span="12">
            <h4 class="section-title">物流轨迹</h4>
            <el-timeline>
              <el-timeline-item
                v-for="(item, index) in currentDetail.trackingHistory"
                :key="index"
                :timestamp="item.time"
                placement="top"
              >
                <div class="tracking-item">
                  <div class="tracking-status">{{ item.status }}</div>
                  <div class="tracking-location" v-if="item.location">{{ item.location }}</div>
                  <div class="tracking-desc">{{ item.description }}</div>
                </div>
              </el-timeline-item>
              <el-timeline-item v-if="!currentDetail.trackingHistory || currentDetail.trackingHistory.length === 0">
                <span class="muted">暂无物流轨迹</span>
              </el-timeline-item>
            </el-timeline>
          </el-col>
          <el-col :span="12">
            <h4 class="section-title">操作日志</h4>
            <el-timeline>
              <el-timeline-item
                v-for="(item, index) in currentDetail.operationLogs"
                :key="index"
                :timestamp="item.time"
                placement="top"
                :color="item.color || '#409EFF'"
              >
                <div class="op-item">
                  <div class="op-action">{{ item.action }}</div>
                  <div class="op-operator">操作人：{{ item.operator }}</div>
                  <div class="op-remark" v-if="item.remark">{{ item.remark }}</div>
                </div>
              </el-timeline-item>
              <el-timeline-item v-if="!currentDetail.operationLogs || currentDetail.operationLogs.length === 0">
                <span class="muted">暂无操作日志</span>
              </el-timeline-item>
            </el-timeline>
          </el-col>
        </el-row>
      </div>
      <div slot="footer">
        <el-button @click="detailDialogVisible = false">关闭</el-button>
      </div>
    </el-dialog>

    <el-dialog
      :visible.sync="reviewDialogVisible"
      :title="'审核代发单'"
      width="520px"
      append-to-body
    >
      <el-form :model="reviewForm" label-width="90px">
        <el-form-item label="审核结果">
          <el-radio-group v-model="reviewForm.result">
            <el-radio label="pass">通过</el-radio>
            <el-radio label="reject">拒绝</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="审核备注" v-if="reviewForm.result === 'reject'">
          <el-input
            v-model="reviewForm.remark"
            type="textarea"
            :rows="4"
            placeholder="请填写拒绝原因"
          />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="reviewDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="reviewLoading" @click="submitReview">确认</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  getDropshipOrders,
  getDropshipStatistics,
  getDropshipStatusOptions,
  getDropshipChannelOptions,
  reviewDropshipOrder,
  batchReviewDropshipOrders,
  pushDropshipOrder,
  batchPushDropshipOrders,
  retryPushDropshipOrder,
  cancelDropshipOrder
} from '@/api/dropship'

export default {
  name: 'OverseaDropshipList',
  data() {
    return {
      loading: false,
      reviewLoading: false,
      detailDialogVisible: false,
      reviewDialogVisible: false,
      currentDetail: null,
      currentReviewId: null,
      selectedIds: [],
      stats: {
        pendingReview: 0,
        pendingPush: 0,
        inTransit: 0,
        exception: 0,
        todayNew: 0,
        completeRate: 0
      },
      filterForm: {
        keyword: '',
        status: [],
        warehouseId: null,
        channel: null,
        country: null,
        dateRange: []
      },
      pagination: {
        currentPage: 1,
        pageSize: 20
      },
      total: 0,
      tableData: [],
      reviewForm: {
        result: 'pass',
        remark: ''
      },
      statusOptions: [],
      warehouseOptions: [],
      channelOptions: [],
      countryOptions: [
        { code: 'US', name: '美国' },
        { code: 'GB', name: '英国' },
        { code: 'DE', name: '德国' },
        { code: 'FR', name: '法国' },
        { code: 'JP', name: '日本' },
        { code: 'CA', name: '加拿大' },
        { code: 'AU', name: '澳大利亚' }
      ]
    }
  },
  computed: {},
  created() {
    this.fetchOptions()
    this.fetchList()
    this.fetchStats()
  },
  methods: {
    async fetchOptions() {
      try {
        const [statusRes, channelRes] = await Promise.all([
          getDropshipStatusOptions(),
          getDropshipChannelOptions()
        ])
        if (statusRes.data?.success) {
          this.statusOptions = statusRes.data.data || []
        }
        if (channelRes.data?.success) {
          this.channelOptions = channelRes.data.data || []
        }
      } catch (e) {
        console.error(e)
      }
    },
    async fetchList() {
      this.loading = true
      try {
        const params = {
          page: this.pagination.currentPage,
          per_page: this.pagination.pageSize
        }
        if (this.filterForm.keyword) params.keyword = this.filterForm.keyword
        if (this.filterForm.status.length > 0) params.status = this.filterForm.status.join(',')
        if (this.filterForm.warehouseId) params.warehouse_id = this.filterForm.warehouseId
        if (this.filterForm.channel) params.source_channel = this.filterForm.channel
        if (this.filterForm.country) params.receiver_country = this.filterForm.country
        if (this.filterForm.dateRange && this.filterForm.dateRange.length === 2) {
          params.date_range = this.filterForm.dateRange
        }
        const res = await getDropshipOrders(params)
        const data = res.data
        if (data.success !== false) {
          const list = data.data || data
          if (Array.isArray(list)) {
            this.tableData = list
            this.total = data.total || list.length
          } else if (list.data) {
            this.tableData = list.data
            this.total = list.total || list.data.length
          }
        }
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async fetchStats() {
      try {
        const res = await getDropshipStatistics()
        const data = res.data
        if (data.success !== false) {
          const d = data.data || data
          this.stats = {
            pendingReview: d.pending_review || 0,
            pendingPush: d.pending_push || 0,
            inTransit: d.in_transit || 0,
            exception: d.exceptions || 0,
            todayNew: d.today?.orders || 0,
            completeRate: d.completion_rate || 0
          }
          this.warehouseOptions = (d.warehouses || []).map(w => ({ id: w.warehouse_id, name: w.warehouse_name }))
        }
      } catch (e) {
        console.error(e)
      }
    },
    getStatusLabel(status) {
      const map = {}
      this.statusOptions.forEach(item => { map[item.value] = item.label })
      return map[status] || status
    },
    getStatusColor(status) {
      const colorMap = {
        draft: 'info',
        pending_review: 'warning',
        auto_review_pass: 'success',
        review_pass: 'success',
        review_reject: 'danger',
        pushing: 'primary',
        push_success: 'success',
        push_failed: 'danger',
        processing: 'primary',
        picked: 'primary',
        packed: 'primary',
        shipped: 'success',
        in_transit: 'warning',
        customs: 'warning',
        delivered: 'success',
        completed: 'success',
        cancelled: 'info',
        returned: 'warning',
        exception: 'danger'
      }
      return colorMap[status] || 'info'
    },
    canReview(status) {
      return status === 'pending_review' || status === 'draft'
    },
    canPush(status) {
      return ['review_pass', 'auto_review_pass', 'push_failed'].includes(status)
    },
    canCancel(status) {
      const transitions = {
        draft: ['cancelled'],
        pending_review: ['cancelled'],
        auto_review_pass: ['cancelled'],
        review_pass: ['cancelled'],
        pushing: [],
        push_failed: ['cancelled'],
        push_success: [],
        processing: ['cancelled'],
        picked: [],
        packed: [],
        shipped: [],
        in_transit: [],
        customs: [],
        delivered: [],
        completed: [],
        cancelled: [],
        returned: [],
        review_reject: [],
        exception: ['cancelled']
      }
      return (transitions[status] || []).includes('cancelled')
    },
    canRetry(status) {
      return ['push_failed', 'exception'].includes(status)
    },
    handleSearch() {
      this.pagination.currentPage = 1
      this.fetchList()
    },
    handleReset() {
      this.filterForm = {
        keyword: '',
        status: [],
        warehouseId: null,
        channel: null,
        country: null,
        dateRange: []
      }
      this.handleSearch()
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
    handleCreate() {
      this.$router.push({ path: '/dropship/orders/create' })
    },
    handleView(row) {
      this.$router.push({ path: `/dropship/orders/${row.id || row.dropship_id}` })
    },
    handleReview(row) {
      this.currentReviewId = row.id
      this.reviewForm = { result: 'pass', remark: '' }
      this.reviewDialogVisible = true
    },
    async submitReview() {
      if (this.reviewForm.result === 'reject' && !this.reviewForm.remark.trim()) {
        this.$message.warning('请填写拒绝原因')
        return
      }
      this.reviewLoading = true
      try {
        await reviewDropshipOrder(this.currentReviewId, {
          pass: this.reviewForm.result === 'pass',
          remark: this.reviewForm.remark
        })
        this.$message.success('审核成功')
        this.reviewDialogVisible = false
        this.fetchList()
        this.fetchStats()
      } catch (e) {
        console.error(e)
      } finally {
        this.reviewLoading = false
      }
    },
    handlePush(row) {
      this.$confirm(`确定要推送代发单【${row.dropshipNo || row.dropship_no}】吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await pushDropshipOrder(row.id)
          this.$message.success('推送成功')
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleCancel(row) {
      this.$prompt('请填写取消原因：', `取消代发单【${row.dropshipNo || row.dropship_no}】`, {
        confirmButtonText: '确定取消',
        cancelButtonText: '返回',
        inputPlaceholder: '请填写取消原因',
        inputPattern: /.+/,
        inputErrorMessage: '取消原因不能为空'
      }).then(async ({ value }) => {
        try {
          await cancelDropshipOrder(row.id, { reason: value })
          this.$message.success('取消成功')
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleRetry(row) {
      this.$confirm(`确定要重试代发单【${row.dropshipNo || row.dropship_no}】吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await retryPushDropshipOrder(row.id)
          this.$message.success('重试成功')
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleBatchReview() {
      this.$confirm(`确定要批量审核选中的 ${this.selectedIds.length} 个代发单吗？`, '批量审核', {
        confirmButtonText: '通过审核',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await batchReviewDropshipOrders({
            ids: this.selectedIds,
            pass: true
          })
          this.$message.success('批量审核成功')
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleBatchPush() {
      this.$confirm(`确定要批量推送选中的 ${this.selectedIds.length} 个代发单吗？`, '批量推送', {
        confirmButtonText: '确定推送',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await batchPushDropshipOrders({ ids: this.selectedIds })
          this.$message.success('批量推送成功')
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.oversea-dropship-list {
  padding: 16px;

  .stat-cards {
    margin-bottom: 16px;
  }

  .stat-card {
    .stat-content {
      display: flex;
      align-items: center;
    }

    .stat-icon {
      width: 56px;
      height: 56px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 16px;
      font-size: 28px;
      color: #fff;

      &.pending-review {
        background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
      }
      &.pending-push {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      }
      &.in-transit {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      }
      &.exception {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      }
      &.today-new {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      }
      &.complete-rate {
        background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
      }
    }

    .stat-info {
      flex: 1;
    }

    .stat-value {
      font-size: 24px;
      font-weight: 600;
      color: #303133;
      line-height: 1.2;
    }

    .stat-label {
      font-size: 13px;
      color: #909399;
      margin-top: 4px;
    }
  }

  .filter-card {
    margin-bottom: 16px;

    ::v-deep .el-form-item {
      margin-bottom: 12px;
    }
  }

  .table-card {
    .table-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .muted {
      color: #909399;
      font-size: 12px;
    }

    .amount {
      font-weight: 500;
      color: #f56c6c;
    }

    .pagination {
      margin-top: 16px;
      text-align: right;
    }
  }

  .detail-dialog {
    .muted {
      color: #909399;
    }

    .section-title {
      font-size: 14px;
      font-weight: 500;
      color: #303133;
      margin: 0 0 12px 0;
      padding-left: 8px;
      border-left: 3px solid #409EFF;
    }

    .tracking-item {
      .tracking-status {
        font-weight: 500;
        color: #303133;
      }
      .tracking-location {
        color: #606266;
        font-size: 12px;
        margin-top: 2px;
      }
      .tracking-desc {
        color: #909399;
        font-size: 12px;
        margin-top: 2px;
      }
    }

    .op-item {
      .op-action {
        font-weight: 500;
        color: #303133;
      }
      .op-operator {
        color: #606266;
        font-size: 12px;
        margin-top: 2px;
      }
      .op-remark {
        color: #909399;
        font-size: 12px;
        margin-top: 2px;
      }
    }
  }
}
</style>
