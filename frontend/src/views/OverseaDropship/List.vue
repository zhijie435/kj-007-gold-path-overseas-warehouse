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
      statusOptions: [
        { value: 'draft', label: '草稿' },
        { value: 'pending_review', label: '待审核' },
        { value: 'auto_review_pass', label: '自动审核通过' },
        { value: 'review_pass', label: '审核通过' },
        { value: 'review_reject', label: '审核拒绝' },
        { value: 'pushing', label: '推单中' },
        { value: 'push_success', label: '推单成功' },
        { value: 'push_failed', label: '推单失败' },
        { value: 'processing', label: '处理中' },
        { value: 'picked', label: '已拣货' },
        { value: 'packed', label: '已打包' },
        { value: 'shipped', label: '已发货' },
        { value: 'in_transit', label: '运输中' },
        { value: 'customs', label: '清关中' },
        { value: 'delivered', label: '已签收' },
        { value: 'completed', label: '已完成' },
        { value: 'cancelled', label: '已取消' },
        { value: 'returned', label: '已退回' },
        { value: 'exception', label: '异常' }
      ],
      warehouseOptions: [
        { id: 1, name: '美国洛杉矶仓' },
        { id: 2, name: '英国伦敦仓' },
        { id: 3, name: '德国法兰克福仓' },
        { id: 4, name: '日本东京仓' }
      ],
      channelOptions: [
        { value: 'shopify', label: 'Shopify' },
        { value: 'amazon', label: 'Amazon' },
        { value: 'ebay', label: 'eBay' },
        { value: 'manual', label: '手动录入' }
      ],
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
    this.fetchList()
    this.fetchStats()
  },
  methods: {
    fetchList() {
      this.loading = true
      setTimeout(() => {
        this.tableData = this.generateMockData()
        this.total = 86
        this.loading = false
      }, 500)
    },
    fetchStats() {
      this.stats = {
        pendingReview: 23,
        pendingPush: 15,
        inTransit: 48,
        exception: 5,
        todayNew: 18,
        completeRate: 86.5
      }
    },
    generateMockData() {
      const statuses = [
        'pending_review', 'review_pass', 'push_success', 'processing',
        'shipped', 'in_transit', 'delivered', 'completed', 'exception'
      ]
      const channels = ['Shopify', 'Amazon', 'eBay', '手动录入']
      const warehouses = ['美国洛杉矶仓', '英国伦敦仓', '德国法兰克福仓', '日本东京仓']
      const countries = ['US', 'GB', 'DE', 'JP', 'CA']
      const firstNames = ['John', 'Emma', 'Michael', 'Sophia', 'James', 'Olivia']
      const lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones']
      const data = []
      for (let i = 0; i < this.pagination.pageSize; i++) {
        const firstName = firstNames[Math.floor(Math.random() * firstNames.length)]
        const lastName = lastNames[Math.floor(Math.random() * lastNames.length)]
        const status = statuses[Math.floor(Math.random() * statuses.length)]
        data.push({
          id: i + 1,
          dropshipNo: 'DS' + new Date().getFullYear() + String(Math.floor(Math.random() * 1000000)).padStart(6, '0'),
          externalOrderNo: Math.random() > 0.3 ? 'EXT' + Math.floor(Math.random() * 100000) : null,
          sourceChannel: channels[Math.floor(Math.random() * channels.length)],
          warehouseName: warehouses[Math.floor(Math.random() * warehouses.length)],
          receiverName: `${firstName} ${lastName}`,
          receiverPhone: '+1' + Math.floor(1000000000 + Math.random() * 900000000),
          receiverCountry: countries[Math.floor(Math.random() * countries.length)],
          totalItems: Math.floor(Math.random() * 5) + 1,
          currency: 'USD',
          totalCost: (Math.random() * 500 + 20).toFixed(2),
          status: status,
          trackingNo: ['shipped', 'in_transit', 'delivered', 'completed'].includes(status)
            ? 'TRK' + Math.floor(Math.random() * 10000000000)
            : null,
          createdAt: this.randomDate()
        })
      }
      return data
    },
    randomDate() {
      const start = new Date()
      start.setDate(start.getDate() - Math.floor(Math.random() * 30))
      return start.toISOString().replace('T', ' ').substring(0, 19)
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
      this.currentDetail = {
        ...row,
        receiverEmail: 'test@example.com',
        receiverState: 'CA',
        receiverCity: 'Los Angeles',
        receiverPostalCode: '90001',
        receiverAddress: '123 Main Street, Apt 4B',
        shippingMethodCode: 'USPS Priority',
        items: [
          { sku: 'SKU001', name: '蓝牙耳机 Pro', spec: '黑色/标准版', quantity: 2, price: 49.99, subtotal: 99.98, weight: 0.3, hsCode: '85176200' },
          { sku: 'SKU002', name: '手机壳', spec: '透明/iPhone 15', quantity: 1, price: 19.99, subtotal: 19.99, weight: 0.05, hsCode: '39269000' }
        ],
        trackingHistory: [
          { status: '已签收', location: 'Los Angeles, US', description: '包裹已送达收件人', time: '2026-06-20 14:30:00' },
          { status: '派送中', location: 'Los Angeles, US', description: '快递员正在派送', time: '2026-06-20 09:15:00' },
          { status: '到达目的地', location: 'Los Angeles, US', description: '包裹已到达当地配送站', time: '2026-06-19 22:00:00' },
          { status: '运输中', location: 'Chicago, US', description: '包裹转运中', time: '2026-06-18 16:00:00' },
          { status: '已发货', location: 'New York, US', description: '包裹已从海外仓发出', time: '2026-06-17 10:00:00' }
        ],
        operationLogs: [
          { action: '订单签收', operator: '系统', time: '2026-06-20 14:30:00', color: '#67C23A' },
          { action: '物流轨迹更新', operator: '系统', time: '2026-06-19 22:00:00' },
          { action: 'WMS推送发货', operator: '系统', time: '2026-06-17 10:00:00', color: '#67C23A' },
          { action: '推单成功', operator: '系统', time: '2026-06-16 18:00:00', color: '#67C23A' },
          { action: '审核通过', operator: 'admin', time: '2026-06-16 15:30:00', color: '#67C23A' },
          { action: '创建代发单', operator: 'admin', time: '2026-06-16 14:00:00' }
        ]
      }
      this.detailDialogVisible = true
    },
    handleReview(row) {
      this.currentReviewId = row.id
      this.reviewForm = { result: 'pass', remark: '' }
      this.reviewDialogVisible = true
    },
    submitReview() {
      if (this.reviewForm.result === 'reject' && !this.reviewForm.remark.trim()) {
        this.$message.warning('请填写拒绝原因')
        return
      }
      this.reviewLoading = true
      setTimeout(() => {
        const row = this.tableData.find(item => item.id === this.currentReviewId)
        if (row) {
          row.status = this.reviewForm.result === 'pass' ? 'review_pass' : 'review_reject'
        }
        this.$message.success('审核成功')
        this.reviewDialogVisible = false
        this.reviewLoading = false
        this.fetchStats()
      }, 500)
    },
    handlePush(row) {
      this.$confirm(`确定要推送代发单【${row.dropshipNo}】吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        row.status = 'pushing'
        setTimeout(() => {
          row.status = 'push_success'
          this.$message.success('推送成功')
          this.fetchStats()
        }, 800)
      }).catch(() => {})
    },
    handleCancel(row) {
      this.$confirm(`确定要取消代发单【${row.dropshipNo}】吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        row.status = 'cancelled'
        this.$message.success('取消成功')
        this.fetchStats()
      }).catch(() => {})
    },
    handleRetry(row) {
      this.$confirm(`确定要重试代发单【${row.dropshipNo}】吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        row.status = 'pushing'
        setTimeout(() => {
          row.status = 'push_success'
          this.$message.success('重试成功')
          this.fetchStats()
        }, 800)
      }).catch(() => {})
    },
    handleBatchReview() {
      this.$confirm(`确定要批量审核选中的 ${this.selectedIds.length} 个代发单吗？`, '批量审核', {
        confirmButtonText: '通过审核',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.tableData.forEach(row => {
          if (this.selectedIds.includes(row.id) && (row.status === 'pending_review' || row.status === 'draft')) {
            row.status = 'review_pass'
          }
        })
        this.$message.success('批量审核成功')
        this.fetchStats()
      }).catch(() => {})
    },
    handleBatchPush() {
      this.$confirm(`确定要批量推送选中的 ${this.selectedIds.length} 个代发单吗？`, '批量推送', {
        confirmButtonText: '确定推送',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.tableData.forEach(row => {
          if (this.selectedIds.includes(row.id) && ['review_pass', 'auto_review_pass', 'push_failed'].includes(row.status)) {
            row.status = 'push_success'
          }
        })
        this.$message.success('批量推送成功')
        this.fetchStats()
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
