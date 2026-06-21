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
        draft: ['pending_review'],
        pending_review: ['review_pass', 'review_reject'],
        auto_review_pass: ['pushing'],
        review_pass: ['pushing'],
        pushing: ['push_success', 'push_failed'],
        push_success: ['processing'],
        processing: ['picked'],
        picked: ['packed'],
        packed: ['shipped'],
        shipped: ['in_transit', 'customs', 'delivered'],
        in_transit: ['customs', 'delivered'],
        customs: ['in_transit', 'delivered'],
        delivered: ['completed'],
        push_failed: ['pushing'],
        exception: ['processing', 'pushing']
      },
      detail: {
        id: 1,
        dropshipNo: 'DS202606201430250001',
        orderNo: 'SO2026062000123',
        externalOrderNo: 'EXT-SHOPIFY-78923',
        wmsOrderNo: 'WMS-LAX-120568',
        sourceChannel: 'Shopify',
        fulfillmentType: '一件代发',
        status: 'in_transit',
        creator: 'admin',
        reviewer: 'admin',
        createdAt: '2026-06-16 14:30:25',
        reviewedAt: '2026-06-16 15:10:00',
        pushedAt: '2026-06-16 18:00:00',
        shippedAt: '2026-06-17 10:00:00',
        deliveredAt: null,
        completedAt: null,
        cancelledAt: null,
        receiverName: 'Emma Johnson',
        receiverPhone: '+1-555-234-5678',
        receiverEmail: 'emma.j@example.com',
        receiverCountry: 'US',
        receiverState: 'CA',
        receiverCity: 'Los Angeles',
        receiverPostalCode: '90001',
        receiverAddress: '123 Main Street, Apt 4B, Beverly Hills',
        warehouseId: 1,
        warehouseName: '美国洛杉矶仓',
        warehouseCode: 'US-LAX-01',
        shippingMethod: 'USPS Priority Mail',
        shippingMethodCode: 'usps_priority',
        trackingNo: '9405511899560123456789',
        carrierName: 'USPS',
        currency: 'USD',
        subtotal: 119.97,
        shippingFee: 12.50,
        handlingFee: 5.00,
        insuranceFee: 2.40,
        dutyFee: 0.00,
        totalCost: 139.87,
        declaredValue: 119.97,
        weight: 0.650,
        volumeWeight: 0.800,
        pushAttempts: 1,
        reviewRemark: '',
        remark: '客户要求周末送达，优先处理',
        items: [
          {
            sku: 'BT-EARP-PRO-BLK',
            name: '蓝牙耳机 Pro 2代 主动降噪',
            spec: '黑色 / 标准版',
            quantity: 2,
            price: 49.99,
            subtotal: 99.98,
            weight: 0.300,
            hsCode: '8517620000',
            batchNo: 'B20260515'
          },
          {
            sku: 'ACC-CASE-IP15-CLR',
            name: '透明手机保护壳',
            spec: '透明 / iPhone 15 Pro',
            quantity: 1,
            price: 19.99,
            subtotal: 19.99,
            weight: 0.050,
            hsCode: '3926909090',
            batchNo: 'B20260601'
          }
        ]
      },
      operationLogs: [
        {
          action: '物流轨迹更新',
          operator: '系统',
          time: '2026-06-20 14:30:25',
          level: 'info',
          remark: '已签收，包裹由收件人本人签收'
        },
        {
          action: '状态更新：已签收',
          operator: '系统',
          time: '2026-06-20 14:30:00',
          level: 'success',
          color: '#67C23A',
          tag: 'Delivered'
        },
        {
          action: '物流轨迹更新',
          operator: '系统',
          time: '2026-06-20 09:15:30',
          level: 'info',
          remark: '快递员正在派送中'
        },
        {
          action: '物流轨迹更新',
          operator: '系统',
          time: '2026-06-19 22:00:00',
          level: 'info',
          remark: '包裹已到达当地配送站'
        },
        {
          action: '状态更新：运输中',
          operator: '系统',
          time: '2026-06-18 16:00:00',
          level: 'warning',
          color: '#E6A23C',
          tag: 'In Transit'
        },
        {
          action: '状态更新：已发货',
          operator: 'WMS系统',
          time: '2026-06-17 10:00:00',
          level: 'success',
          color: '#67C23A',
          tag: 'Shipped',
          remark: '运单号：9405511899560123456789'
        },
        {
          action: 'WMS回调：已打包',
          operator: 'WMS系统',
          time: '2026-06-17 08:30:00',
          level: 'primary',
          tag: 'Packed'
        },
        {
          action: 'WMS回调：已拣货',
          operator: 'WMS系统',
          time: '2026-06-17 06:00:00',
          level: 'primary',
          tag: 'Picked'
        },
        {
          action: '推单成功',
          operator: '系统',
          time: '2026-06-16 18:00:00',
          level: 'success',
          color: '#67C23A',
          tag: 'Pushed',
          remark: 'WMS单号：WMS-LAX-120568'
        },
        {
          action: '开始推单到WMS',
          operator: '系统',
          time: '2026-06-16 17:59:50',
          level: 'primary',
          tag: 'Pushing'
        },
        {
          action: '审核通过',
          operator: 'admin',
          time: '2026-06-16 15:10:00',
          level: 'success',
          color: '#67C23A',
          tag: 'Reviewed',
          remark: '信息完整，通过审核'
        },
        {
          action: '提交待审核',
          operator: 'admin',
          time: '2026-06-16 14:35:00',
          level: 'warning',
          color: '#E6A23C',
          tag: 'Pending'
        },
        {
          action: '创建代发单',
          operator: 'admin',
          time: '2026-06-16 14:30:25',
          level: 'info',
          tag: 'Created',
          remark: '来源：Shopify订单同步'
        }
      ],
      trackingLogs: [
        {
          status: '已签收 Delivered',
          location: 'Los Angeles, CA 90001, US',
          description: 'Your item was delivered at the front door or porch',
          time: '2026-06-20 14:30:25'
        },
        {
          status: '派送中 Out for Delivery',
          location: 'Los Angeles, CA 90001, US',
          description: 'Your item is out for delivery today',
          time: '2026-06-20 09:15:30'
        },
        {
          status: '到达目的地 Arrived at Destination',
          location: 'Los Angeles Processing Center, CA, US',
          description: 'Your package has arrived at the destination processing facility',
          time: '2026-06-19 22:00:00'
        },
        {
          status: '运输中 In Transit',
          location: 'Phoenix Distribution Hub, AZ, US',
          description: 'Your package is traveling to the destination',
          time: '2026-06-18 16:00:00'
        },
        {
          status: '离开发件地 Departed Origin',
          location: 'Los Angeles International Hub, CA, US',
          description: 'Your package has left the origin facility',
          time: '2026-06-17 18:30:00'
        },
        {
          status: '已发货 Shipped',
          location: 'US-LAX-01 美国洛杉矶仓',
          description: 'The shipment has been dispatched from the overseas warehouse',
          time: '2026-06-17 10:00:00'
        },
        {
          status: '包裹揽收 Accepted',
          location: 'USPS Pickup Point - Los Angeles, CA',
          description: 'USPS has accepted the package',
          time: '2026-06-17 11:45:00'
        }
      ]
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
      return this.detail.status === 'pending_review'
    },
    canPush() {
      return ['review_pass', 'auto_review_pass', 'push_failed'].includes(this.detail.status)
    },
    canRetry() {
      return ['push_failed', 'exception'].includes(this.detail.status)
    },
    canCancel() {
      return !['completed', 'cancelled', 'returned', 'review_reject', 'shipped', 'in_transit', 'customs', 'delivered'].includes(this.detail.status)
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
  created() {},
  methods: {
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
      setTimeout(() => {
        loading.close()
        this.$message.success('物流轨迹已更新，共获取到 ' + this.trackingLogs.length + ' 条记录')
      }, 1200)
    },
    handleBack() {
      this.$router.back()
    },
    handleReviewPass() {
      this.$confirm('确定审核通过此代发单吗？', '审核通过', {
        confirmButtonText: '通过',
        cancelButtonText: '取消',
        type: 'success'
      }).then(() => {
        this.reviewLoading = true
        setTimeout(() => {
          this.detail.status = 'review_pass'
          this.detail.reviewedAt = this.getNow()
          this.detail.reviewer = '当前用户'
          this.operationLogs.unshift({
            action: '审核通过',
            operator: '当前用户',
            time: this.getNow(),
            level: 'success',
            color: '#67C23A',
            tag: 'Reviewed',
            remark: '手动操作：审核通过'
          })
          this.reviewLoading = false
          this.$message.success('审核成功')
        }, 500)
      }).catch(() => {})
    },
    handlePush() {
      this.$confirm('确定推送到WMS系统吗？', '推送确认', {
        confirmButtonText: '推送',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.pushLoading = true
        setTimeout(() => {
          this.detail.status = 'push_success'
          this.detail.pushedAt = this.getNow()
          this.detail.wmsOrderNo = 'WMS-' + Math.floor(Math.random() * 1000000)
          this.operationLogs.unshift({
            action: '推单成功',
            operator: '系统',
            time: this.getNow(),
            level: 'success',
            color: '#67C23A',
            tag: 'Pushed',
            remark: 'WMS单号：' + this.detail.wmsOrderNo
          })
          this.pushLoading = false
          this.$message.success('推送成功')
        }, 1000)
      }).catch(() => {})
    },
    handleStatusCommand(status) {
      this.$confirm(`确定将状态更新为【${this.getStatusLabel(status)}】吗？`, '状态更新', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.detail.status = status
        if (status === 'shipped') {
          this.detail.shippedAt = this.getNow()
          this.detail.trackingNo = 'TRK' + Math.floor(Math.random() * 10000000000)
        }
        if (status === 'delivered') {
          this.detail.deliveredAt = this.getNow()
        }
        if (status === 'completed') {
          this.detail.completedAt = this.getNow()
        }
        this.operationLogs.unshift({
          action: `手动更新状态：${this.getStatusLabel(status)}`,
          operator: '当前用户',
          time: this.getNow(),
          level: 'primary',
          tag: 'Manual'
        })
        this.$message.success('状态已更新')
      }).catch(() => {})
    },
    handleRetry() {
      this.$confirm('确定重试此操作吗？', '重试确认', {
        confirmButtonText: '重试',
        cancelButtonText: '取消',
        type: 'info'
      }).then(() => {
        this.detail.status = 'pushing'
        setTimeout(() => {
          this.detail.status = 'push_success'
          this.operationLogs.unshift({
            action: '重试推单成功',
            operator: '系统',
            time: this.getNow(),
            level: 'success',
            color: '#67C23A',
            tag: 'Retried'
          })
          this.$message.success('重试成功')
        }, 1000)
      }).catch(() => {})
    },
    handleCancel() {
      this.$prompt('请填写取消原因：', '取消订单', {
        confirmButtonText: '确定取消',
        cancelButtonText: '返回',
        inputPlaceholder: '请填写取消原因',
        inputPattern: /.+/,
        inputErrorMessage: '取消原因不能为空',
        type: 'error',
        inputType: 'textarea',
        inputValidator: (value) => !!value && value.trim().length >= 2
      }).then(({ value }) => {
        this.detail.status = 'cancelled'
        this.detail.cancelledAt = this.getNow()
        this.operationLogs.unshift({
          action: '订单已取消',
          operator: '当前用户',
          time: this.getNow(),
          level: 'danger',
          color: '#F56C6C',
          tag: 'Cancelled',
          remark: value
        })
        this.$message.success('订单已取消')
      }).catch(() => {})
    },
    getNow() {
      const d = new Date()
      return d.toISOString().replace('T', ' ').substring(0, 19)
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
