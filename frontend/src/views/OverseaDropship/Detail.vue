<template>
  <div class="oversea-dropship-detail">
    <div class="detail-header">
      <div class="header-left">
        <el-button type="text" icon="el-icon-back" @click="handleBack">返回列表</el-button>
        <el-divider direction="vertical" />
        <div class="header-title-group">
          <div class="title-row">
            <h2 class="order-title">代发单详情</h2>
            <el-tag size="medium" effect="dark" :type="getStatusTagColor(detail.status)">
              {{ getStatusLabel(detail.status) }}
            </el-tag>
            <el-tag size="medium" type="info" effect="plain" class="order-no-tag">
              {{ detail.dropshipNo }}
            </el-tag>
          </div>
          <div class="title-sub muted">
            创建于 {{ detail.createdAt }} · 创建人：{{ detail.creator }}
          </div>
        </div>
      </div>
      <el-affix :offset-top="16" class="header-affix">
        <div class="action-group">
          <el-button
            type="primary"
            icon="el-icon-check"
            :disabled="!canReview"
            :loading="reviewLoading"
            @click="handleReviewPass"
          >审核通过</el-button>
          <el-button
            type="warning"
            icon="el-icon-upload"
            :disabled="!canPush"
            :loading="pushLoading"
            @click="handlePush"
          >推送到WMS</el-button>
          <el-dropdown
            @command="handleStatusCommand"
            :disabled="!canUpdateStatus"
            trigger="click"
          >
            <el-button icon="el-icon-switch-button">
              更新状态<i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item command="processing" :disabled="!canTransitionTo('processing')">处理中</el-dropdown-item>
              <el-dropdown-item command="picked" :disabled="!canTransitionTo('picked')">已拣货</el-dropdown-item>
              <el-dropdown-item command="packed" :disabled="!canTransitionTo('packed')">已打包</el-dropdown-item>
              <el-dropdown-item command="shipped" :disabled="!canTransitionTo('shipped')">已发货</el-dropdown-item>
              <el-dropdown-item command="in_transit" :disabled="!canTransitionTo('in_transit')">运输中</el-dropdown-item>
              <el-dropdown-item command="customs" :disabled="!canTransitionTo('customs')">清关中</el-dropdown-item>
              <el-dropdown-item command="delivered" :disabled="!canTransitionTo('delivered')">已签收</el-dropdown-item>
              <el-dropdown-item command="completed" :disabled="!canTransitionTo('completed')">已完成</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
          <el-button
            icon="el-icon-refresh-right"
            :disabled="!canRetry"
            @click="handleRetry"
          >重试</el-button>
          <el-button
            type="danger"
            icon="el-icon-close"
            :disabled="!canCancel"
            @click="handleCancel"
          >取消订单</el-button>
        </div>
      </el-affix>
    </div>

    <el-card class="status-steps-card" shadow="never">
      <el-steps
        :active="currentStepIndex"
        finish-status="success"
        align-center
        class="progress-steps"
      >
        <el-step
          v-for="(step, index) in progressSteps"
          :key="step.status"
          :title="step.label"
          :status="getStepStatus(index)"
        />
      </el-steps>
    </el-card>

    <el-row :gutter="16">
      <el-col :xs="24" :md="16">
        <el-card class="info-card" shadow="never">
          <div slot="header" class="card-header">
            <i class="el-icon-document-copy header-icon"></i>
            <span>订单基础信息</span>
          </div>
          <el-descriptions :column="3" border size="small">
            <el-descriptions-item label="代发单号">
              <span class="mono">{{ detail.dropshipNo }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="关联订单号">
              <span v-if="detail.orderNo" class="mono">{{ detail.orderNo }}</span>
              <span v-else class="muted">-</span>
            </el-descriptions-item>
            <el-descriptions-item label="外部单号">
              <span v-if="detail.externalOrderNo" class="mono">{{ detail.externalOrderNo }}</span>
              <span v-else class="muted">-</span>
            </el-descriptions-item>
            <el-descriptions-item label="来源渠道">{{ detail.sourceChannel }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ detail.createdAt }}</el-descriptions-item>
            <el-descriptions-item label="创建人">{{ detail.creator }}</el-descriptions-item>
            <el-descriptions-item label="履约方式">
              <el-tag size="mini" type="success">海外仓一件代发</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="审核时间">
              {{ detail.reviewedAt || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="审核人">
              {{ detail.reviewer || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="推单时间">
              {{ detail.pushedAt || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="WMS单号">
              <span v-if="detail.wmsOrderNo" class="mono">{{ detail.wmsOrderNo }}</span>
              <span v-else class="muted">-</span>
            </el-descriptions-item>
            <el-descriptions-item label="完成时间">
              {{ detail.completedAt || '-' }}
            </el-descriptions-item>
          </el-descriptions>
        </el-card>

        <el-card class="info-card" shadow="never">
          <div slot="header" class="card-header">
            <i class="el-icon-location-outline header-icon receiver"></i>
            <span>收件人信息</span>
          </div>
          <div class="receiver-info">
            <div class="receiver-main">
              <span class="receiver-name">{{ detail.receiverName }}</span>
              <span class="receiver-phone muted">{{ detail.receiverPhone }}</span>
              <el-tag v-if="detail.receiverEmail" size="mini" type="info" effect="plain" style="margin-left: 8px">
                <i class="el-icon-message"></i> {{ detail.receiverEmail }}
              </el-tag>
            </div>
            <div class="receiver-address">
              <i class="el-icon-location-information"></i>
              <span>
                {{ getCountryName(detail.receiverCountry) }} {{ detail.receiverState }} {{ detail.receiverCity }}
                {{ detail.receiverPostalCode }} {{ detail.receiverAddress }}
              </span>
            </div>
          </div>
        </el-card>

        <el-card class="info-card" shadow="never">
          <div slot="header" class="card-header">
            <i class="el-icon-goods header-icon"></i>
            <span>商品明细</span>
            <div class="header-right-info">
              共 <span class="num">{{ itemSummary.totalRows }}</span> 行，
              <span class="num">{{ itemSummary.totalQty }}</span> 件商品
            </div>
          </div>
          <el-table :data="detail.items" border size="small">
            <el-table-column type="index" label="#" width="50" align="center" />
            <el-table-column prop="sku" label="SKU" width="140">
              <template slot-scope="{ row }">
                <span class="mono">{{ row.sku }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="name" label="商品名称" min-width="200" show-overflow-tooltip />
            <el-table-column prop="spec" label="规格" width="130" show-overflow-tooltip />
            <el-table-column prop="quantity" label="数量" width="80" align="center" />
            <el-table-column label="单价" width="100" align="right">
              <template slot-scope="{ row }">${{ row.price.toFixed(2) }}</template>
            </el-table-column>
            <el-table-column label="小计" width="110" align="right">
              <template slot-scope="{ row }">
                <span class="amount">${{ row.subtotal.toFixed(2) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="weight" label="重量(kg)" width="100" align="right" />
            <el-table-column prop="hsCode" label="HS编码" width="120">
              <template slot-scope="{ row }">
                <span v-if="row.hsCode" class="mono">{{ row.hsCode }}</span>
                <span v-else class="muted">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="batchNo" label="批次号" width="120">
              <template slot-scope="{ row }">
                <span v-if="row.batchNo" class="mono">{{ row.batchNo }}</span>
                <span v-else class="muted">-</span>
              </template>
            </el-table-column>
          </el-table>
        </el-card>

        <el-card class="info-card" shadow="never">
          <div slot="header" class="card-header">
            <i class="el-icon-truck header-icon shipping"></i>
            <span>物流与费用</span>
          </div>
          <el-descriptions :column="3" border size="small">
            <el-descriptions-item label="海外仓">
              <div>{{ detail.warehouseName }}</div>
              <div class="muted text-xs">{{ detail.warehouseCode || '' }}</div>
            </el-descriptions-item>
            <el-descriptions-item label="物流渠道">{{ detail.shippingMethod }}</el-descriptions-item>
            <el-descriptions-item label="运单号">
              <div v-if="detail.trackingNo">
                <span class="mono tracking-no">{{ detail.trackingNo }}</span>
                <el-button
                  type="text"
                  size="mini"
                  icon="el-icon-copy-document"
                  @click="copyTrackingNo"
                >复制</el-button>
              </div>
              <span v-else class="muted">-</span>
            </el-descriptions-item>
            <el-descriptions-item label="承运商">{{ detail.carrierName || '-' }}</el-descriptions-item>
            <el-descriptions-item label="发货时间">{{ detail.shippedAt || '-' }}</el-descriptions-item>
            <el-descriptions-item label="签收时间">{{ detail.deliveredAt || '-' }}</el-descriptions-item>
          </el-descriptions>

          <div class="fee-section">
            <h4 class="section-title">费用明细 <span class="currency-tag">({{ detail.currency }})</span></h4>
            <el-row :gutter="16">
              <el-col :xs="12" :sm="8">
                <div class="fee-box">
                  <div class="fee-label">商品金额</div>
                  <div class="fee-value">${{ detail.subtotal.toFixed(2) }}</div>
                </div>
              </el-col>
              <el-col :xs="12" :sm="8">
                <div class="fee-box">
                  <div class="fee-label">运费</div>
                  <div class="fee-value">${{ detail.shippingFee.toFixed(2) }}</div>
                </div>
              </el-col>
              <el-col :xs="12" :sm="8">
                <div class="fee-box">
                  <div class="fee-label">操作费</div>
                  <div class="fee-value">${{ detail.handlingFee.toFixed(2) }}</div>
                </div>
              </el-col>
              <el-col :xs="12" :sm="8">
                <div class="fee-box">
                  <div class="fee-label">保险费</div>
                  <div class="fee-value">${{ detail.insuranceFee.toFixed(2) }}</div>
                </div>
              </el-col>
              <el-col :xs="12" :sm="8">
                <div class="fee-box">
                  <div class="fee-label">关税</div>
                  <div class="fee-value">${{ detail.dutyFee.toFixed(2) }}</div>
                </div>
              </el-col>
              <el-col :xs="12" :sm="8">
                <div class="fee-box total">
                  <div class="fee-label">订单总费用</div>
                  <div class="fee-value">${{ detail.totalCost.toFixed(2) }}</div>
                </div>
              </el-col>
            </el-row>
            <div class="declared-row muted text-sm">
              <i class="el-icon-warning-outline"></i>
              申报价值：{{ detail.currency }} {{ detail.declaredValue.toFixed(2) }}，
              实际重量：{{ detail.weight }}kg，
              体积重：{{ detail.volumeWeight }}kg
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="8">
        <el-card class="timeline-card" shadow="never">
          <div slot="header" class="card-header">
            <i class="el-icon-time header-icon operation"></i>
            <span>操作日志</span>
          </div>
          <el-timeline class="op-timeline">
            <el-timeline-item
              v-for="(log, index) in operationLogs"
              :key="'op-' + index"
              :timestamp="log.time"
              placement="top"
              :type="getLogType(log.level)"
              :color="log.color"
              :hollow="log.level === 'info'"
            >
              <div class="log-item">
                <div class="log-title">
                  {{ log.action }}
                  <el-tag v-if="log.tag" :type="log.tagType || 'info'" size="mini" effect="plain" style="margin-left: 6px">
                    {{ log.tag }}
                  </el-tag>
                </div>
                <div class="log-meta">
                  <i class="el-icon-user-solid"></i> {{ log.operator }}
                </div>
                <div v-if="log.remark" class="log-remark">
                  <i class="el-icon-chat-dot-round"></i> {{ log.remark }}
                </div>
              </div>
            </el-timeline-item>
            <el-timeline-item v-if="operationLogs.length === 0" type="info">
              <span class="muted">暂无操作记录</span>
            </el-timeline-item>
          </el-timeline>
        </el-card>

        <el-card class="timeline-card" shadow="never">
          <div slot="header" class="card-header">
            <i class="el-icon-map-location header-icon tracking"></i>
            <span>物流轨迹</span>
            <div class="header-right-info">
              <el-button type="text" size="mini" icon="el-icon-refresh" @click="refreshTracking">
                刷新
              </el-button>
            </div>
          </div>
          <el-timeline class="tracking-timeline">
            <el-timeline-item
              v-for="(track, index) in trackingLogs"
              :key="'track-' + index"
              :timestamp="track.time"
              placement="top"
              :type="index === 0 ? 'success' : 'primary'"
              :hollow="index > 0"
              size="small"
            >
              <div class="track-item">
                <div class="track-title" :class="{ latest: index === 0 }">
                  {{ track.status }}
                </div>
                <div v-if="track.location" class="track-location">
                  <i class="el-icon-location"></i> {{ track.location }}
                </div>
                <div class="track-desc muted" v-if="track.description">
                  {{ track.description }}
                </div>
              </div>
            </el-timeline-item>
            <el-timeline-item v-if="trackingLogs.length === 0" type="info" size="small">
              <span class="muted">暂无物流轨迹</span>
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import {
  getDropshipOrder,
  reviewDropshipOrder,
  pushDropshipOrder,
  retryPushDropshipOrder,
  cancelDropshipOrder,
  updateDropshipOrderStatus,
  syncDropshipTracking
} from '@/api/dropship'

export default {
  name: 'OverseaDropshipDetail',
  data() {
    return {
      reviewLoading: false,
      pushLoading: false,
      progressSteps: [
        { status: 'draft', label: '草稿' },
        { status: 'pending_review', label: '待审核' },
        { status: 'review_pass', label: '审核通过' },
        { status: 'pushing', label: '推单' },
        { status: 'processing', label: '处理中' },
        { status: 'shipped', label: '已发货' },
        { status: 'in_transit', label: '运输中' },
        { status: 'delivered', label: '签收' },
        { status: 'completed', label: '完成' }
      ],
      transitions: {
        draft: ['pending_review', 'cancelled'],
        pending_review: ['auto_review_pass', 'review_pass', 'review_reject', 'cancelled'],
        auto_review_pass: ['pushing', 'cancelled'],
        review_pass: ['pushing', 'cancelled'],
        pushing: ['push_success', 'push_failed', 'exception'],
        push_failed: ['pushing', 'cancelled', 'exception'],
        push_success: ['processing', 'exception'],
        processing: ['picked', 'exception', 'cancelled'],
        picked: ['packed', 'exception'],
        packed: ['shipped', 'exception'],
        shipped: ['in_transit', 'customs', 'delivered', 'returned', 'exception'],
        in_transit: ['customs', 'delivered', 'returned', 'exception'],
        customs: ['in_transit', 'delivered', 'exception'],
        delivered: ['completed', 'returned', 'exception'],
        completed: [],
        cancelled: [],
        returned: [],
        review_reject: [],
        exception: ['processing', 'cancelled', 'pushing']
      },
      detail: {
        id: null,
        dropshipNo: '',
        orderNo: '',
        externalOrderNo: '',
        wmsOrderNo: '',
        sourceChannel: '',
        fulfillmentType: '',
        status: 'draft',
        creator: '',
        reviewer: '',
        createdAt: '',
        reviewedAt: '',
        pushedAt: '',
        shippedAt: '',
        deliveredAt: '',
        completedAt: '',
        cancelledAt: '',
        receiverName: '',
        receiverPhone: '',
        receiverEmail: '',
        receiverCountry: '',
        receiverState: '',
        receiverCity: '',
        receiverPostalCode: '',
        receiverAddress: '',
        warehouseId: null,
        warehouseName: '',
        warehouseCode: '',
        shippingMethod: '',
        shippingMethodCode: '',
        trackingNo: '',
        carrierName: '',
        currency: 'USD',
        subtotal: 0,
        shippingFee: 0,
        handlingFee: 0,
        insuranceFee: 0,
        dutyFee: 0,
        totalCost: 0,
        declaredValue: 0,
        weight: 0,
        volumeWeight: 0,
        pushAttempts: 0,
        reviewRemark: '',
        remark: '',
        items: []
      },
      operationLogs: [],
      trackingLogs: []
    }
  },
  computed: {
    currentStepIndex() {
      const statusOrder = this.progressSteps.map(s => s.status)
      const currentIndex = statusOrder.indexOf(this.detail.status)
      if (currentIndex >= 0) return currentIndex + 1
      const map = {
        auto_review_pass: 3,
        push_success: 5,
        push_failed: 4,
        picked: 5,
        packed: 5,
        customs: 7,
        cancelled: 0,
        returned: 8,
        review_reject: 1,
        exception: 5
      }
      return map[this.detail.status] || 0
    },
    canReview() {
      return this.canTransitionTo('review_pass') || this.canTransitionTo('review_reject')
    },
    canPush() {
      return this.canTransitionTo('pushing')
    },
    canRetry() {
      return this.canTransitionTo('pushing') && ['push_failed', 'exception'].includes(this.detail.status)
    },
    canCancel() {
      return this.canTransitionTo('cancelled')
    },
    canUpdateStatus() {
      return ['push_success', 'processing', 'picked', 'packed', 'shipped', 'in_transit', 'customs', 'delivered'].includes(this.detail.status)
    },
    itemSummary() {
      return {
        totalRows: this.detail.items.length,
        totalQty: this.detail.items.reduce((sum, it) => sum + it.quantity, 0)
      }
    }
  },
  created() {
    this.loadOrder()
  },
  methods: {
    async loadOrder() {
      const id = this.$route.params.id
      if (!id) return
      try {
        const res = await getDropshipOrder(id)
        const data = res.data
        const d = data.data || data
        this.detail = {
          id: d.id,
          dropshipNo: d.dropship_no || d.dropshipNo || '',
          orderNo: d.order_id || '',
          externalOrderNo: d.external_order_no || d.externalOrderNo || '',
          wmsOrderNo: d.wms_order_no || d.wmsOrderNo || '',
          sourceChannel: d.source_channel || d.sourceChannel || '',
          fulfillmentType: d.fulfillment_type || '',
          status: d.status || 'draft',
          creator: d.creator?.name || d.created_by || '',
          reviewer: d.reviewer?.name || d.reviewed_by || '',
          createdAt: d.created_at || d.createdAt || '',
          reviewedAt: d.reviewed_at || d.reviewedAt || '',
          pushedAt: d.pushed_at || d.pushedAt || '',
          shippedAt: d.shipped_at || d.shippedAt || '',
          deliveredAt: d.delivered_at || d.deliveredAt || '',
          completedAt: d.completed_at || d.completedAt || '',
          cancelledAt: d.cancelled_at || d.cancelledAt || '',
          receiverName: d.receiver_name || d.receiverName || '',
          receiverPhone: d.receiver_phone || d.receiverPhone || '',
          receiverEmail: d.receiver_email || d.receiverEmail || '',
          receiverCountry: d.receiver_country || d.receiverCountry || '',
          receiverState: d.receiver_state || d.receiverState || '',
          receiverCity: d.receiver_city || d.receiverCity || '',
          receiverPostalCode: d.receiver_postal_code || d.receiverPostalCode || '',
          receiverAddress: d.receiver_address || d.receiverAddress || '',
          warehouseId: d.warehouse_id || d.warehouseId,
          warehouseName: d.warehouse?.name || d.warehouseName || '',
          warehouseCode: d.warehouse?.code || d.warehouseCode || '',
          shippingMethod: d.shipping_method_code || d.shippingMethod || '',
          shippingMethodCode: d.shipping_method_code || d.shippingMethodCode || '',
          trackingNo: d.tracking_no || d.trackingNo || '',
          carrierName: d.carrier_name || d.carrierName || '',
          currency: d.currency || 'USD',
          subtotal: parseFloat(d.subtotal) || 0,
          shippingFee: parseFloat(d.shipping_fee) || 0,
          handlingFee: parseFloat(d.handling_fee) || 0,
          insuranceFee: parseFloat(d.insurance_fee) || 0,
          dutyFee: parseFloat(d.duty_fee) || 0,
          totalCost: parseFloat(d.total_cost) || 0,
          declaredValue: parseFloat(d.declared_value) || 0,
          weight: parseFloat(d.weight) || 0,
          volumeWeight: parseFloat(d.volume_weight) || 0,
          pushAttempts: d.push_attempts || 0,
          reviewRemark: d.review_remark || '',
          remark: d.remark || '',
          items: (d.items || []).map(item => ({
            sku: item.sku || '',
            name: item.product_name || item.name || '',
            spec: item.specification || item.spec || '',
            quantity: parseInt(item.quantity) || 0,
            price: parseFloat(item.unit_price || item.price) || 0,
            subtotal: parseFloat(item.subtotal) || 0,
            weight: parseFloat(item.weight) || 0,
            hsCode: item.hs_code || item.hsCode || '',
            batchNo: item.batch_no || item.batchNo || ''
          }))
        }
        this.operationLogs = (d.callback_logs || []).map(log => ({
          action: log.callback_type || log.action || '',
          operator: log.source || '系统',
          time: log.created_at || log.time || '',
          level: 'info',
          color: '#409EFF',
          remark: log.request_body || ''
        }))
        this.trackingLogs = (d.tracking_events || []).map(ev => ({
          status: ev.status || '',
          location: ev.location || '',
          description: ev.description || '',
          time: ev.occurred_at || ev.time || ''
        }))
      } catch (e) {
        this.$message.error('加载订单详情失败')
        console.error(e)
      }
    },
    getStepStatus(index) {
      if (index < this.currentStepIndex - 1) return 'success'
      if (index === this.currentStepIndex - 1) return 'process'
      return ''
    },
    getStatusLabel(status) {
      const map = {
        draft: '草稿',
        pending_review: '待审核',
        auto_review_pass: '自动审核通过',
        review_pass: '审核通过',
        review_reject: '审核拒绝',
        pushing: '推单中',
        push_success: '推单成功',
        push_failed: '推单失败',
        processing: '处理中',
        picked: '已拣货',
        packed: '已打包',
        shipped: '已发货',
        in_transit: '运输中',
        customs: '清关中',
        delivered: '已签收',
        completed: '已完成',
        cancelled: '已取消',
        returned: '已退回',
        exception: '异常'
      }
      return map[status] || status
    },
    getStatusTagColor(status) {
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
    canTransitionTo(target) {
      const allowed = this.transitions[this.detail.status] || []
      return allowed.includes(target)
    },
    getLogType(level) {
      const map = {
        success: 'success',
        warning: 'warning',
        danger: 'danger',
        primary: 'primary',
        info: 'info'
      }
      return map[level] || 'info'
    },
    getCountryName(code) {
      const map = {
        US: '🇺🇸 美国',
        GB: '🇬🇧 英国',
        DE: '🇩🇪 德国',
        FR: '🇫🇷 法国',
        JP: '🇯🇵 日本',
        CA: '🇨🇦 加拿大',
        AU: '🇦🇺 澳大利亚',
        IT: '🇮🇹 意大利',
        ES: '🇪🇸 西班牙'
      }
      return map[code] || code
    },
    copyTrackingNo() {
      const input = document.createElement('textarea')
      input.value = this.detail.trackingNo
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
      this.$message.success('运单号已复制')
    },
    refreshTracking() {
      const loading = this.$loading({
        lock: true,
        text: '正在刷新物流轨迹...',
        spinner: 'el-icon-loading',
        background: 'rgba(0, 0, 0, 0.5)'
      })
      syncDropshipTracking(this.detail.id).then(res => {
        const data = res.data
        if (data.success !== false) {
          const events = (data.data || {}).tracking_events || []
          this.trackingLogs = events.map(ev => ({
            status: ev.status || '',
            location: ev.location || '',
            description: ev.description || '',
            time: ev.occurred_at || ev.time || ''
          }))
          this.$message.success('物流轨迹已更新，共获取到 ' + this.trackingLogs.length + ' 条记录')
        }
      }).catch(e => {
        console.error(e)
        this.$message.error('刷新物流轨迹失败')
      }).finally(() => {
        loading.close()
      })
    },
    handleBack() {
      this.$router.back()
    },
    handleReviewPass() {
      this.$confirm('确定审核通过此代发单吗？', '审核通过', {
        confirmButtonText: '通过',
        cancelButtonText: '取消',
        type: 'success'
      }).then(async () => {
        this.reviewLoading = true
        try {
          await reviewDropshipOrder(this.detail.id, { pass: true })
          this.$message.success('审核成功')
          this.loadOrder()
        } catch (e) {
          console.error(e)
        } finally {
          this.reviewLoading = false
        }
      }).catch(() => {})
    },
    handlePush() {
      this.$confirm('确定推送到WMS系统吗？', '推送确认', {
        confirmButtonText: '推送',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        if (!this.canTransitionTo('pushing')) {
          this.$message.warning('当前状态不允许推送')
          return
        }
        this.pushLoading = true
        try {
          await pushDropshipOrder(this.detail.id)
          this.$message.success('推送成功')
          this.loadOrder()
        } catch (e) {
          console.error(e)
        } finally {
          this.pushLoading = false
        }
      }).catch(() => {})
    },
    handleStatusCommand(status) {
      if (!this.canTransitionTo(status)) {
        this.$message.warning(`当前状态不允许切换到【${this.getStatusLabel(status)}】`)
        return
      }
      this.$confirm(`确定将状态更新为【${this.getStatusLabel(status)}】吗？`, '状态更新', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await updateDropshipOrderStatus(this.detail.id, { status })
          this.$message.success('状态已更新')
          this.loadOrder()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleRetry() {
      this.$confirm('确定重试此操作吗？', '重试确认', {
        confirmButtonText: '重试',
        cancelButtonText: '取消',
        type: 'info'
      }).then(async () => {
        if (!this.canTransitionTo('pushing')) {
          this.$message.warning('当前状态不允许重试')
          return
        }
        try {
          await retryPushDropshipOrder(this.detail.id)
          this.$message.success('重试成功')
          this.loadOrder()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleCancel() {
      if (!this.canTransitionTo('cancelled')) {
        this.$message.warning('当前状态不允许取消')
        return
      }
      this.$prompt('请填写取消原因：', '取消订单', {
        confirmButtonText: '确定取消',
        cancelButtonText: '返回',
        inputPlaceholder: '请填写取消原因',
        inputPattern: /.+/,
        inputErrorMessage: '取消原因不能为空',
        type: 'error',
        inputType: 'textarea',
        inputValidator: (value) => !!value && value.trim().length >= 2
      }).then(async ({ value }) => {
        try {
          await cancelDropshipOrder(this.detail.id, { reason: value })
          this.$message.success('订单已取消')
          this.loadOrder()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.oversea-dropship-detail {
  padding: 16px;

  .detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    background: #fff;
    padding: 16px 20px;
    border-radius: 6px;
    margin-bottom: 16px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);

    .header-left {
      display: flex;
      align-items: center;
      flex: 1;
    }

    .header-title-group {
      margin-left: 8px;

      .title-row {
        display: flex;
        align-items: center;
        gap: 12px;

        .order-title {
          margin: 0;
          font-size: 18px;
          font-weight: 600;
          color: #303133;
        }

        .order-no-tag {
          font-family: 'SFMono-Regular', Consolas, monospace;
          font-size: 12px;
        }
      }

      .title-sub {
        margin-top: 6px;
        font-size: 13px;
      }
    }

    .action-group {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
      max-width: 600px;
    }
  }

  .status-steps-card {
    margin-bottom: 16px;

    ::v-deep .el-card__body {
      overflow-x: auto;
      overflow-y: hidden;
    }

    .progress-steps {
      padding: 8px 0;
      min-width: 900px;
      white-space: nowrap;
    }
  }

  .info-card {
    margin-bottom: 16px;

    .card-header {
      display: flex;
      align-items: center;
      font-size: 14px;
      font-weight: 500;
      color: #303133;

      .header-icon {
        margin-right: 8px;
        font-size: 16px;
        color: #409EFF;

        &.receiver { color: #67C23A; }
        &.shipping { color: #E6A23C; }
        &.operation { color: #909399; }
        &.tracking { color: #F56C6C; }
      }

      .header-right-info {
        margin-left: auto;
        font-size: 12px;
        color: #606266;
        font-weight: normal;

        .num {
          color: #409EFF;
          font-weight: 600;
          font-size: 14px;
          margin: 0 2px;
        }
      }
    }

    .receiver-info {
      .receiver-main {
        margin-bottom: 12px;

        .receiver-name {
          font-size: 16px;
          font-weight: 600;
          color: #303133;
          margin-right: 16px;
        }
      }

      .receiver-address {
        background: #f5f7fa;
        padding: 12px 16px;
        border-radius: 4px;
        color: #606266;
        line-height: 1.6;

        i {
          color: #67C23A;
          margin-right: 6px;
        }
      }
    }

    .mono {
      font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace;
      font-size: 13px;
    }

    .muted {
      color: #909399;
    }

    .amount {
      color: #67C23A;
      font-weight: 500;
    }

    .tracking-no {
      font-weight: 500;
      color: #409EFF;
    }

    .fee-section {
      margin-top: 20px;

      .section-title {
        font-size: 14px;
        font-weight: 500;
        color: #303133;
        margin: 0 0 12px 0;
        padding-left: 8px;
        border-left: 3px solid #E6A23C;

        .currency-tag {
          font-size: 12px;
          color: #909399;
          font-weight: normal;
          margin-left: 6px;
        }
      }
    }

    .fee-box {
      background: #fafafa;
      border-radius: 6px;
      padding: 12px 16px;
      text-align: center;
      margin-bottom: 12px;
      border: 1px solid #ebeef5;

      &.total {
        background: linear-gradient(135deg, #fef0f0 0%, #fff 100%);
        border: 1px solid #fbc4c4;
      }

      .fee-label {
        font-size: 12px;
        color: #909399;
        margin-bottom: 6px;
      }

      .fee-value {
        font-size: 18px;
        font-weight: 600;
        color: #303133;
      }

      &.total .fee-value {
        color: #F56C6C;
        font-size: 22px;
      }
    }

    .declared-row {
      margin-top: 12px;
      padding: 10px 12px;
      background: #fffdf5;
      border-radius: 4px;
      border: 1px solid #faecd8;

      i {
        color: #E6A23C;
        margin-right: 4px;
      }
    }

    .text-sm {
      font-size: 13px;
    }
  }

  .timeline-card {
    margin-bottom: 16px;

    .card-header {
      display: flex;
      align-items: center;
      font-size: 14px;
      font-weight: 500;
      color: #303133;

      .header-icon {
        margin-right: 8px;
        font-size: 16px;

        &.operation { color: #909399; }
        &.tracking { color: #F56C6C; }
      }

      .header-right-info {
        margin-left: auto;
      }
    }

    .op-timeline {
      ::v-deep .el-timeline-item__timestamp {
        font-size: 11px;
        color: #909399;
      }

      .log-item {
        .log-title {
          font-size: 13px;
          font-weight: 500;
          color: #303133;
        }

        .log-meta {
          font-size: 11px;
          color: #606266;
          margin-top: 3px;

          i {
            margin-right: 3px;
          }
        }

        .log-remark {
          font-size: 12px;
          color: #909399;
          margin-top: 5px;
          padding: 6px 10px;
          background: #f5f7fa;
          border-radius: 4px;
          line-height: 1.5;

          i {
            color: #409EFF;
            margin-right: 3px;
          }
        }
      }
    }

    .tracking-timeline {
      ::v-deep .el-timeline-item__timestamp {
        font-size: 11px;
        color: #909399;
      }

      .track-item {
        .track-title {
          font-size: 13px;
          color: #606266;
          font-weight: 500;

          &.latest {
            color: #67C23A;
            font-size: 14px;
          }
        }

        .track-location {
          font-size: 12px;
          color: #409EFF;
          margin-top: 3px;

          i {
            margin-right: 3px;
          }
        }

        .track-desc {
          font-size: 12px;
          color: #909399;
          margin-top: 3px;
          line-height: 1.5;
        }
      }
    }
  }

  .muted {
    color: #909399;
  }
}
</style>
