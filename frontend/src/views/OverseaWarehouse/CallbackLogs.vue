<template>
  <div class="wms-callback-logs">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">WMS回调日志</h2>
        <p class="page-desc">海外仓WMS系统回调记录和处理状态监控</p>
      </div>
      <div class="header-right">
        <el-button type="primary" :loading="retryingAll" @click="handleRetryAllFailed">
          <i class="el-icon-refresh"></i> 重试全部失败
        </el-button>
      </div>
    </div>

    <el-row :gutter="16" class="stats-row">
      <el-col :span="6" v-for="stat in statsCards" :key="stat.key">
        <el-card shadow="hover" class="stat-card" :class="'card-' + stat.color">
          <div class="stat-icon"><i :class="stat.icon"></i></div>
          <div class="stat-info">
            <div class="stat-value">{{ stat.value }}</div>
            <div class="stat-label">{{ stat.label }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="回调类型">
          <el-select v-model="queryParams.callback_type" placeholder="全部" clearable style="width: 140px">
            <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 140px">
            <el-option label="已接收" value="received" />
            <el-option label="处理中" value="processing" />
            <el-option label="成功" value="success" />
            <el-option label="失败" value="failed" />
            <el-option label="待重试" value="retry" />
          </el-select>
        </el-form-item>
        <el-form-item label="仓库">
          <el-select v-model="queryParams.warehouse_id" placeholder="全部" clearable filterable style="width: 160px">
            <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="WMS单号/代发单/参考号"
            clearable
            style="width: 200px"
            @keyup.enter.native="handleSearch"
          />
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="dateRange"
            type="datetimerange"
            range-separator="至"
            start-placeholder="开始时间"
            end-placeholder="结束时间"
            value-format="yyyy-MM-dd HH:mm:ss"
            size="small"
            style="width: 340px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" size="small" @click="handleSearch">
            <i class="el-icon-search"></i> 搜索
          </el-button>
          <el-button size="small" @click="handleReset">
            <i class="el-icon-refresh"></i> 重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" border stripe>
        <el-table-column type="index" label="#" width="60" align="center" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="回调类型" width="120">
          <template slot-scope="s">
            <el-tag :type="getCallbackTypeColor(s.row.callback_type)" size="small">
              {{ getCallbackTypeLabel(s.row.callback_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template slot-scope="s">
            <el-tag :type="getStatusType(s.row.status)" size="small">{{ getStatusLabel(s.row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="warehouse_name" label="仓库" width="140" />
        <el-table-column prop="wms_provider" label="服务商" width="100" />
        <el-table-column prop="wms_order_no" label="WMS单号" width="160" show-overflow-tooltip />
        <el-table-column prop="dropship_no" label="代发单号" width="160" show-overflow-tooltip />
        <el-table-column prop="reference_no" label="参考号" width="140" show-overflow-tooltip />
        <el-table-column prop="retry_count" label="重试" width="70" align="center">
          <template slot-scope="s">
            <el-badge v-if="s.row.retry_count > 0" :value="s.row.retry_count" :max="5" class="retry-badge">
              <span>{{ s.row.retry_count }}</span>
            </el-badge>
            <span v-else>0</span>
          </template>
        </el-table-column>
        <el-table-column label="错误" min-width="160" show-overflow-tooltip>
          <template slot-scope="s">
            <span v-if="s.row.error_message" style="color: #f56c6c">{{ s.row.error_message }}</span>
            <span v-else style="color: #909399">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="source_ip" label="来源IP" width="130" />
        <el-table-column prop="created_at" label="接收时间" width="170" />
        <el-table-column prop="processed_at" label="处理时间" width="170">
          <template slot-scope="s">{{ s.row.processed_at || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template slot-scope="s">
            <el-button type="primary" link size="mini" @click="handleView(s.row)">查看</el-button>
            <el-button
              v-if="s.row.status === 'failed' || s.row.status === 'retry'"
              type="warning"
              link
              size="mini"
              @click="handleRetry(s.row)"
            >重试</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="queryParams.page"
          v-model:page-size="queryParams.per_page"
          :page-sizes="[20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @size-change="fetchData"
          @current-change="fetchData"
        />
      </div>
    </el-card>

    <el-dialog :visible.sync="detailVisible" title="回调详情" width="900px" append-to-body>
      <div v-if="currentLog">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="ID">{{ currentLog.id }}</el-descriptions-item>
          <el-descriptions-item label="回调类型">
            <el-tag :type="getCallbackTypeColor(currentLog.callback_type)" size="small">
              {{ getCallbackTypeLabel(currentLog.callback_type) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(currentLog.status)" size="small">
              {{ getStatusLabel(currentLog.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="重试次数">{{ currentLog.retry_count || 0 }}</el-descriptions-item>
          <el-descriptions-item label="WMS服务商">{{ currentLog.wms_provider }}</el-descriptions-item>
          <el-descriptions-item label="仓库">{{ currentLog.warehouse_name }}</el-descriptions-item>
          <el-descriptions-item label="WMS单号">{{ currentLog.wms_order_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="代发单号">{{ currentLog.dropship_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="参考号">{{ currentLog.reference_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="请求ID">{{ currentLog.request_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="来源IP">{{ currentLog.source_ip || '-' }}</el-descriptions-item>
          <el-descriptions-item label="处理人">{{ currentLog.processor_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="接收时间" :span="2">{{ currentLog.created_at }}</el-descriptions-item>
          <el-descriptions-item label="处理时间" :span="2">{{ currentLog.processed_at || '-' }}</el-descriptions-item>
          <el-descriptions-item v-if="currentLog.error_message" label="错误码" :span="1">
            {{ currentLog.error_code || '-' }}
          </el-descriptions-item>
          <el-descriptions-item v-if="currentLog.error_message" label="错误信息" :span="1">
            <span style="color: #f56c6c">{{ currentLog.error_message }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left">请求头</el-divider>
        <pre class="code-block">{{ currentLog.request_headers || '-' }}</pre>

        <el-divider content-position="left">请求体</el-divider>
        <pre class="code-block">{{ prettyPrint(currentLog.request_body) }}</pre>

        <el-divider content-position="left">响应体</el-divider>
        <pre class="code-block">{{ prettyPrint(currentLog.response_body) || '-' }}</pre>
      </div>
      <template slot="footer">
        <el-button
          v-if="currentLog && (currentLog.status === 'failed' || currentLog.status === 'retry')"
          type="warning"
          @click="handleRetry(currentLog)"
        >重试处理</el-button>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { getWmsCallbackLogs, getWmsCallbackLog, retryWmsCallbackLog, getWmsCallbackStatistics } from '@/api/wmsCallback'
import { getWarehouseConfigs } from '@/api/overseaWarehouse'

const callbackTypeMap = {
  inventory: { label: '库存同步', color: 'warning' },
  shipment: { label: '发货通知', color: 'success' },
  tracking: { label: '物流轨迹', color: 'primary' },
  order_status: { label: '订单状态', color: 'info' },
  stock_adjust: { label: '库存调整', color: 'danger' }
}

const statusMap = {
  received: { label: '已接收', type: 'info' },
  processing: { label: '处理中', type: 'warning' },
  success: { label: '成功', type: 'success' },
  failed: { label: '失败', type: 'danger' },
  retry: { label: '待重试', type: 'warning' }
}

export default {
  name: 'WmsCallbackLogs',
  data() {
    return {
      loading: false,
      retryingAll: false,
      tableData: [],
      total: 0,
      dateRange: [],
      detailVisible: false,
      currentLog: null,
      typeOptions: Object.entries(callbackTypeMap).map(([v, { label }]) => ({ value: v, label })),
      warehouseOptions: [],
      statistics: { total: 0, success: 0, failed: 0, pending: 0, today: 0, retry_rate: 0 },
      queryParams: {
        page: 1,
        per_page: 20,
        callback_type: '',
        status: '',
        warehouse_id: '',
        keyword: ''
      }
    }
  },
  computed: {
    statsCards() {
      return [
        { key: 'total', label: '总回调数', value: this.statistics.total || 0, icon: 'el-icon-document', color: 'blue' },
        { key: 'success', label: '处理成功', value: this.statistics.success || 0, icon: 'el-icon-circle-check', color: 'green' },
        { key: 'failed', label: '处理失败', value: this.statistics.failed || 0, icon: 'el-icon-circle-close', color: 'red' },
        { key: 'pending', label: '待处理', value: this.statistics.pending || 0, icon: 'el-icon-time', color: 'orange' }
      ]
    }
  },
  created() {
    this.fetchData()
    this.fetchStatistics()
    this.fetchWarehouses()
  },
  methods: {
    getCallbackTypeLabel(type) { return callbackTypeMap[type]?.label || type },
    getCallbackTypeColor(type) { return callbackTypeMap[type]?.color || 'info' },
    getStatusLabel(status) { return statusMap[status]?.label || status },
    getStatusType(status) { return statusMap[status]?.type || 'info' },
    prettyPrint(json) {
      if (!json) return ''
      try {
        return JSON.stringify(JSON.parse(json), null, 2)
      } catch {
        return json
      }
    },
    async fetchData() {
      this.loading = true
      try {
        const params = { ...this.queryParams }
        if (this.dateRange?.length === 2) {
          params.start_time = this.dateRange[0]
          params.end_time = this.dateRange[1]
        }
        Object.keys(params).forEach(k => {
          if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k]
        })
        const res = await getWmsCallbackLogs(params)
        this.tableData = res.data?.list || []
        this.total = res.data?.total || 0
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async fetchStatistics() {
      try {
        const res = await getWmsCallbackStatistics()
        Object.assign(this.statistics, res.data || {})
      } catch (e) {
        console.error(e)
      }
    },
    async fetchWarehouses() {
      try {
        const res = await getWarehouseConfigs({ per_page: 100 })
        this.warehouseOptions = res.data?.list || []
      } catch (e) {
        console.error(e)
      }
    },
    handleSearch() {
      this.queryParams.page = 1
      this.fetchData()
    },
    handleReset() {
      this.queryParams = {
        page: 1,
        per_page: 20,
        callback_type: '',
        status: '',
        warehouse_id: '',
        keyword: ''
      }
      this.dateRange = []
      this.fetchData()
    },
    async handleView(row) {
      try {
        const res = await getWmsCallbackLog(row.id)
        this.currentLog = res.data || row
        this.detailVisible = true
      } catch (e) {
        this.currentLog = row
        this.detailVisible = true
      }
    },
    async handleRetry(row) {
      this.$confirm(`确认重试处理 ID=${row.id} 的回调吗？`, '重试确认', {
        confirmButtonText: '确认重试',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await retryWmsCallbackLog(row.id)
          this.$message.success('已加入处理队列')
          this.fetchData()
          this.fetchStatistics()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleRetryAllFailed() {
      this.$confirm('确认重试所有处理失败的回调吗？', '批量重试确认', {
        confirmButtonText: '确认重试',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        this.retryingAll = true
        try {
          await this.$axios?.post?.('/wms-callback-logs/batch-retry') || this.fetchData()
          this.$message.success('批量重试操作已提交')
          this.fetchData()
          this.fetchStatistics()
        } catch (e) {
          console.error(e)
        } finally {
          this.retryingAll = false
        }
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.wms-callback-logs {
  .stats-row {
    margin-bottom: 16px;
    .stat-card {
      display: flex;
      align-items: center;
      padding: 10px 20px;
      .stat-icon {
        font-size: 36px;
        margin-right: 16px;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
      }
      &.card-blue .stat-icon { background: #eaf2ff; color: #409eff; }
      &.card-green .stat-icon { background: #e7f7ee; color: #67c23a; }
      &.card-red .stat-icon { background: #fdecec; color: #f56c6c; }
      &.card-orange .stat-icon { background: #fff3e6; color: #e6a23c; }
      .stat-info {
        .stat-value { font-size: 24px; font-weight: 600; line-height: 1.2; }
        .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
      }
    }
  }
  .filter-card, .table-card { margin-bottom: 16px; }
  .pagination {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
  }
  .code-block {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 4px;
    max-height: 300px;
    overflow: auto;
    font-size: 12px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
  }
  .retry-badge { color: inherit; }
}
</style>
