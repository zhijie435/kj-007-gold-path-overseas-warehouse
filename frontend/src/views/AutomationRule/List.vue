<template>
  <div class="automation-rule-list">
    <el-row :gutter="16" class="stat-cards">
      <el-col :xs="12" :md="8">
        <el-card shadow="hover" class="stat-card stat-total">
          <div class="stat-content">
            <div class="stat-icon">
              <i class="el-icon-video-play"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.totalTriggered.toLocaleString() }}</div>
              <div class="stat-label">执行总次数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :md="8">
        <el-card shadow="hover" class="stat-card stat-success">
          <div class="stat-content">
            <div class="stat-icon">
              <i class="el-icon-circle-check"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.successRate }}%</div>
              <div class="stat-label">执行成功率</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :md="8">
        <el-card shadow="hover" class="stat-card stat-today">
          <div class="stat-content">
            <div class="stat-icon">
              <i class="el-icon-date"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.todayTriggered }}</div>
              <div class="stat-label">今日执行数</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="page-card" shadow="never">
      <div class="page-toolbar">
        <el-button type="primary" icon="el-icon-plus" @click="handleAdd">
          创建规则
        </el-button>
      </div>

      <el-tabs v-model="activeTab" class="rule-tabs" @tab-click="handleTabChange">
        <el-tab-pane label="全部" name="all" />
        <el-tab-pane label="订单处理" name="order_process" />
        <el-tab-pane label="WMS集成" name="wms_integration" />
        <el-tab-pane label="异常处理" name="exception" />
        <el-tab-pane label="消息通知" name="notification" />
      </el-tabs>

      <el-table
        v-loading="loading"
        :data="filteredTableData"
        border
        stripe
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="name" label="规则名称" width="200">
          <template slot-scope="{ row }">
            <el-link type="primary" @click="handleView(row)">{{ row.name }}</el-link>
            <div class="rule-code muted text-xs">{{ row.code }}</div>
          </template>
        </el-table-column>
        <el-table-column label="规则类型" width="140" align="center">
          <template slot-scope="{ row }">
            <el-tag :type="getRuleTypeTag(row.category)" size="small">
              {{ getRuleTypeLabel(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="priority" label="优先级" width="80" align="center">
          <template slot-scope="{ row }">
            <el-badge :value="row.priority" class="priority-badge" :max="999" />
          </template>
        </el-table-column>
        <el-table-column prop="warehouseName" label="绑定仓库" width="130">
          <template slot-scope="{ row }">
            <span v-if="row.warehouseName">{{ row.warehouseName }}</span>
            <span v-else class="muted">全部仓库</span>
          </template>
        </el-table-column>
        <el-table-column prop="countryCode" label="适用国家" width="100">
          <template slot-scope="{ row }">
            <span v-if="row.countryCode">{{ row.countryCode }}</span>
            <span v-else class="muted">全部</span>
          </template>
        </el-table-column>
        <el-table-column prop="sourceChannel" label="适用渠道" width="100">
          <template slot-scope="{ row }">
            <span v-if="row.sourceChannel">{{ row.sourceChannel }}</span>
            <span v-else class="muted">全部</span>
          </template>
        </el-table-column>
        <el-table-column label="金额范围" width="140" align="center">
          <template slot-scope="{ row }">
            <span v-if="row.minAmount || row.maxAmount">
              {{ row.minAmount ? '¥' + row.minAmount : '0' }}
              ~
              {{ row.maxAmount ? '¥' + row.maxAmount : '∞' }}
            </span>
            <span v-else class="muted">不限</span>
          </template>
        </el-table-column>
        <el-table-column label="生效时间" width="150">
          <template slot-scope="{ row }">
            <span v-if="row.activeTimeStart || row.activeTimeEnd">
              {{ row.activeTimeStart || '00:00' }}~{{ row.activeTimeEnd || '23:59' }}
            </span>
            <span v-else class="muted">全天</span>
            <div class="weekdays muted text-xs" v-if="row.weekdays && row.weekdays.length > 0">
              {{ formatWeekdays(row.weekdays) }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template slot-scope="{ row }">
            <el-switch
              v-model="row.isEnabled"
              active-color="#67C23A"
              inactive-color="#DCDFE6"
              @change="handleToggleStatus(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="triggerCount" label="触发次数" width="90" align="center" />
        <el-table-column label="成功率" width="90" align="center">
          <template slot-scope="{ row }">
            <el-progress
              :percentage="row.triggerCount > 0 ? Math.round((row.successCount / row.triggerCount) * 100) : 0"
              :stroke-width="10"
              :show-text="false"
            />
            <span class="text-xs">{{ row.triggerCount > 0 ? Math.round((row.successCount / row.triggerCount) * 100) : 0 }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="lastTriggeredAt" label="最后触发" width="160" />
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template slot-scope="{ row }">
            <el-button type="text" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" size="small" @click="handleCopy(row)">复制</el-button>
            <el-button type="text" size="small" @click="handleTrigger(row)">触发</el-button>
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
      :title="dialogTitle"
      width="860px"
      append-to-body
      custom-class="rule-form-dialog"
      :close-on-click-modal="false"
    >
      <el-form
        ref="ruleForm"
        :model="formData"
        :rules="formRules"
        label-width="120px"
        label-position="right"
      >
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="规则名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入规则名称" maxlength="50" show-word-limit />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="规则编码" prop="code">
              <el-input v-model="formData.code" placeholder="自动生成或自定义" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="规则类型" prop="type">
              <el-select v-model="formData.type" placeholder="请选择规则类型" style="width: 100%">
                <el-option-group
                  v-for="(group, key) in groupedRuleTypes"
                  :key="key"
                  :label="key"
                >
                  <el-option
                    v-for="item in group"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  >
                    <span>{{ item.label }}</span>
                    <span class="muted float-right text-xs">{{ item.description }}</span>
                  </el-option>
                </el-option-group>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="优先级">
              <el-input-number v-model="formData.priority" :min="1" :max="999" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="描述">
          <el-input
            v-model="formData.description"
            type="textarea"
            :rows="2"
            placeholder="请输入规则描述"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>

        <el-divider content-position="left">
          <span class="divider-title">
            <i class="el-icon-filter"></i> 条件配置
          </span>
        </el-divider>

        <div class="condition-editor">
          <div class="condition-group">
            <div class="group-header">
              <el-radio-group v-model="formData.conditionLogic" size="small">
                <el-radio-button label="AND">满足所有条件</el-radio-button>
                <el-radio-button label="OR">满足任一条件</el-radio-button>
              </el-radio-group>
              <el-button
                size="mini"
                type="text"
                icon="el-icon-plus"
                @click="addConditionGroup"
              >添加条件组</el-button>
            </div>

            <div
              v-for="(group, groupIndex) in formData.conditions"
              :key="groupIndex"
              class="group-body"
            >
              <div class="group-title" v-if="formData.conditions.length > 1">
                <span>条件组 {{ groupIndex + 1 }}</span>
                <el-button
                  size="mini"
                  type="text"
                  style="color: #f56c6c"
                  @click="removeConditionGroup(groupIndex)"
                  v-if="formData.conditions.length > 1"
                >删除组</el-button>
              </div>
              <div
                v-for="(condition, conditionIndex) in group.rules"
                :key="conditionIndex"
                class="condition-row"
              >
                <el-select
                  v-model="condition.field"
                  placeholder="字段"
                  size="small"
                  style="width: 180px"
                >
                  <el-option label="订单国家" value="country" />
                  <el-option label="订单金额" value="amount" />
                  <el-option label="商品件数" value="totalItems" />
                  <el-option label="订单来源" value="sourceChannel" />
                  <el-option label="SKU列表" value="skuList" />
                  <el-option label="收货国家" value="receiverCountry" />
                  <el-option label="邮编范围" value="postalCode" />
                  <el-option label="订单状态" value="status" />
                  <el-option label="物流渠道" value="shippingMethod" />
                </el-select>
                <el-select
                  v-model="condition.operator"
                  placeholder="运算符"
                  size="small"
                  style="width: 140px"
                >
                  <el-option label="等于" value="eq" />
                  <el-option label="不等于" value="neq" />
                  <el-option label="大于" value="gt" />
                  <el-option label="大于等于" value="gte" />
                  <el-option label="小于" value="lt" />
                  <el-option label="小于等于" value="lte" />
                  <el-option label="包含" value="in" />
                  <el-option label="不包含" value="not_in" />
                  <el-option label="区间" value="between" />
                  <el-option label="包含任一" value="contains_any" />
                </el-select>
                <el-input
                  v-model="condition.value"
                  placeholder="值"
                  size="small"
                  style="flex: 1"
                />
                <el-button
                  size="mini"
                  type="text"
                  style="color: #f56c6c; flex-shrink: 0"
                  icon="el-icon-delete"
                  @click="removeCondition(groupIndex, conditionIndex)"
                  v-if="group.rules.length > 1"
                />
              </div>
              <div class="condition-footer">
                <el-button size="mini" type="text" icon="el-icon-plus" @click="addCondition(groupIndex)">
                  添加条件
                </el-button>
              </div>
            </div>
          </div>
        </div>

        <el-divider content-position="left">
          <span class="divider-title">
            <i class="el-icon-set-up"></i> 动作配置
          </span>
        </el-divider>

        <el-form-item label="执行动作">
          <el-select
            v-model="formData.actions"
            multiple
            placeholder="选择触发后执行的动作"
            style="width: 100%"
          >
            <el-option label="自动通过审核" value="auto_review" />
            <el-option label="分配指定仓库" value="assign_warehouse" />
            <el-option label="分配指定物流" value="assign_shipping" />
            <el-option label="自动推送WMS" value="push_wms" />
            <el-option label="拆分订单" value="split_order" />
            <el-option label="合并订单" value="combine_order" />
            <el-option label="同步物流轨迹" value="sync_tracking" />
            <el-option label="发送邮件通知" value="send_email" />
            <el-option label="发送短信通知" value="send_sms" />
            <el-option label="创建内部备注" value="add_note" />
            <el-option label="调用Webhook" value="call_webhook" />
          </el-select>
        </el-form-item>

        <el-divider content-position="left">
          <span class="divider-title">
            <i class="el-icon-setting"></i> 适用范围
          </span>
        </el-divider>

        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="绑定仓库">
              <el-select v-model="formData.warehouseId" placeholder="不选=全部仓库" clearable style="width: 100%">
                <el-option label="美国洛杉矶仓" :value="1" />
                <el-option label="英国伦敦仓" :value="2" />
                <el-option label="德国法兰克福仓" :value="3" />
                <el-option label="日本东京仓" :value="4" />
                <el-option label="加拿大多伦多仓" :value="5" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="适用国家">
              <el-select v-model="formData.countryCode" placeholder="不选=全部国家" clearable style="width: 100%">
                <el-option label="美国(US)" value="US" />
                <el-option label="英国(GB)" value="GB" />
                <el-option label="德国(DE)" value="DE" />
                <el-option label="法国(FR)" value="FR" />
                <el-option label="日本(JP)" value="JP" />
                <el-option label="加拿大(CA)" value="CA" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="适用渠道">
              <el-select v-model="formData.sourceChannel" placeholder="不选=全部渠道" clearable style="width: 100%">
                <el-option label="Shopify" value="shopify" />
                <el-option label="Amazon" value="amazon" />
                <el-option label="eBay" value="ebay" />
                <el-option label="手动录入" value="manual" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="最小金额">
              <el-input-number v-model="formData.minAmount" :min="0" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="最大金额">
              <el-input-number v-model="formData.maxAmount" :min="0" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="生效开始时间">
              <el-time-picker
                v-model="formData.activeTimeStart"
                format="HH:mm"
                value-format="HH:mm"
                placeholder="选择开始时间"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="生效结束时间">
              <el-time-picker
                v-model="formData.activeTimeEnd"
                format="HH:mm"
                value-format="HH:mm"
                placeholder="选择结束时间"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="生效星期">
              <el-checkbox-group v-model="formData.weekdays">
                <el-checkbox :label="1">周一</el-checkbox>
                <el-checkbox :label="2">周二</el-checkbox>
                <el-checkbox :label="3">周三</el-checkbox>
                <el-checkbox :label="4">周四</el-checkbox>
                <el-checkbox :label="5">周五</el-checkbox>
                <el-checkbox :label="6">周六</el-checkbox>
                <el-checkbox :label="7">周日</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">
          <span class="divider-title">
            <i class="el-icon-s-operation"></i> 其他设置
          </span>
        </el-divider>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="启用规则">
              <el-switch v-model="formData.isEnabled" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="停止后续规则">
              <el-switch v-model="formData.stopChain" />
              <div class="muted text-xs block mt-4">
                开启后，本规则匹配成功将跳过后续优先级更低的规则
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div slot="footer">
        <el-button @click="formDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">保存</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  getAutomationRules,
  getAutomationRule,
  createAutomationRule,
  updateAutomationRule,
  deleteAutomationRule,
  toggleAutomationRuleEnabled,
  triggerAutomationRule,
  getAutomationRuleTypeOptions,
  getAutomationStatistics
} from '@/api/automationRule'

export default {
  name: 'AutomationRuleList',
  data() {
    return {
      loading: false,
      submitLoading: false,
      activeTab: 'all',
      formDialogVisible: false,
      isViewMode: false,
      isEditMode: false,
      editingId: null,
      stats: {
        totalTriggered: 0,
        successRate: 0,
        todayTriggered: 0
      },
      pagination: {
        currentPage: 1,
        pageSize: 10
      },
      total: 0,
      tableData: [],
      formData: this.createEmptyForm(),
      formRules: {
        name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
        code: [{ required: true, message: '请输入规则编码', trigger: 'blur' }],
        type: [{ required: true, message: '请选择规则类型', trigger: 'change' }]
      },
      groupedRuleTypes: {
        '订单处理': [
          { value: 'auto_review', label: '自动审核', description: '满足条件自动通过审核' },
          { value: 'auto_assign_warehouse', label: '自动分仓', description: '自动分配最优海外仓' },
          { value: 'auto_assign_shipping', label: '自动分配物流', description: '自动分配最优物流渠道' },
          { value: 'auto_split_order', label: '自动拆单', description: '按规则自动拆分订单' },
          { value: 'auto_combine_order', label: '自动合单', description: '同地址自动合并发货' }
        ],
        'WMS集成': [
          { value: 'auto_push_wms', label: '自动推单到WMS', description: '审核后自动推送到WMS' },
          { value: 'auto_sync_tracking', label: '自动同步物流轨迹', description: '定时拉取物流轨迹' },
          { value: 'auto_sync_inventory', label: '自动同步库存', description: '定时从WMS同步库存' }
        ],
        '异常处理': [
          { value: 'auto_cancel_order', label: '自动取消订单', description: '超时未处理自动取消' }
        ],
        '消息通知': [
          { value: 'auto_notification', label: '自动通知', description: '状态变更触发消息通知' }
        ]
      }
    }
  },
  computed: {
    dialogTitle() {
      if (this.isViewMode) return '规则详情'
      return this.isEditMode ? '编辑规则' : '创建规则'
    },
    filteredTableData() {
      if (this.activeTab === 'all') return this.tableData
      const categoryMap = {
        order_process: '订单处理',
        wms_integration: 'WMS集成',
        exception: '异常处理',
        notification: '消息通知'
      }
      const targetCategory = categoryMap[this.activeTab]
      return this.tableData.filter(item => item.category === targetCategory)
    }
  },
  created() {
    this.fetchTypeOptions()
    this.fetchList()
    this.fetchStats()
  },
  methods: {
    createEmptyForm() {
      return {
        name: '',
        code: '',
        type: '',
        description: '',
        priority: 100,
        conditionLogic: 'AND',
        conditions: [
          {
            logic: 'AND',
            rules: [{ field: '', operator: '', value: '' }]
          }
        ],
        actions: [],
        warehouseId: null,
        countryCode: null,
        sourceChannel: null,
        minAmount: null,
        maxAmount: null,
        activeTimeStart: null,
        activeTimeEnd: null,
        weekdays: [],
        isEnabled: true,
        stopChain: false
      }
    },
    async fetchTypeOptions() {
      try {
        const res = await getAutomationRuleTypeOptions()
        const data = res.data
        if (data.success !== false && data.data) {
          this.groupedRuleTypes = data.data
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
        if (this.activeTab !== 'all') {
          params.category = this.activeTab
        }
        const res = await getAutomationRules(params)
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
        const res = await getAutomationStatistics()
        const data = res.data
        if (data.success !== false) {
          const d = data.data || data
          this.stats = {
            totalTriggered: d.total_triggered || d.totalTriggered || 0,
            successRate: d.success_rate || d.successRate || 0,
            todayTriggered: d.today_triggered || d.todayTriggered || 0
          }
        }
      } catch (e) {
        console.error(e)
      }
    },
    getRuleTypeLabel(type) {
      for (const category in this.groupedRuleTypes) {
        const found = this.groupedRuleTypes[category].find(item => item.value === type)
        if (found) return found.label
      }
      return type
    },
    getRuleTypeTag(category) {
      const colorMap = {
        '订单处理': 'primary',
        'WMS集成': 'success',
        '异常处理': 'danger',
        '消息通知': 'warning'
      }
      return colorMap[category] || 'info'
    },
    formatWeekdays(weekdays) {
      const map = { 1: '一', 2: '二', 3: '三', 4: '四', 5: '五', 6: '六', 7: '日' }
      const sorted = [...weekdays].sort((a, b) => a - b)
      return '周' + sorted.map(d => map[d]).join('/')
    },
    handleTabChange() {
      this.pagination.currentPage = 1
      this.fetchList()
    },
    handleSizeChange(size) {
      this.pagination.pageSize = size
      this.fetchList()
    },
    handleCurrentChange(page) {
      this.pagination.currentPage = page
      this.fetchList()
    },
    handleAdd() {
      this.isViewMode = false
      this.isEditMode = false
      this.editingId = null
      this.formData = this.createEmptyForm()
      this.formData.code = 'RULE_' + Date.now().toString().slice(-8)
      this.$nextTick(() => {
        this.$refs.ruleForm && this.$refs.ruleForm.clearValidate()
      })
      this.formDialogVisible = true
    },
    async handleView(row) {
      this.isViewMode = true
      this.isEditMode = false
      try {
        const res = await getAutomationRule(row.id)
        const data = res.data
        const d = data.data || data
        this.formData = this.mapRuleToForm(d)
      } catch (e) {
        this.formData = this.mapRuleToForm(row)
        console.error(e)
      }
      this.$nextTick(() => {
        this.$refs.ruleForm && this.$refs.ruleForm.clearValidate()
      })
      this.formDialogVisible = true
    },
    async handleEdit(row) {
      this.isViewMode = false
      this.isEditMode = true
      this.editingId = row.id
      try {
        const res = await getAutomationRule(row.id)
        const data = res.data
        const d = data.data || data
        this.formData = this.mapRuleToForm(d)
      } catch (e) {
        this.formData = this.mapRuleToForm(row)
        console.error(e)
      }
      this.$nextTick(() => {
        this.$refs.ruleForm && this.$refs.ruleForm.clearValidate()
      })
      this.formDialogVisible = true
    },
    mapRuleToForm(d) {
      return {
        name: d.name || '',
        code: d.code || '',
        type: d.type || '',
        description: d.description || '',
        priority: d.priority || 100,
        conditionLogic: d.conditions?.logic || 'AND',
        conditions: (d.conditions?.groups && d.conditions.groups.length > 0)
          ? d.conditions.groups
          : [{ logic: 'AND', rules: [{ field: '', operator: '', value: '' }] }],
        actions: d.actions || [],
        warehouseId: d.warehouse_id || d.warehouseId || null,
        countryCode: d.country_code || d.countryCode || null,
        sourceChannel: d.source_channel || d.sourceChannel || null,
        minAmount: d.min_amount || d.minAmount || null,
        maxAmount: d.max_amount || d.maxAmount || null,
        activeTimeStart: d.active_time_start || d.activeTimeStart || null,
        activeTimeEnd: d.active_time_end || d.activeTimeEnd || null,
        weekdays: d.weekdays || [],
        isEnabled: d.is_enabled !== undefined ? d.is_enabled : (d.isEnabled !== undefined ? d.isEnabled : true),
        stopChain: d.stop_chain || d.stopChain || false
      }
    },
    handleCopy(row) {
      this.$confirm(`确定要复制规则【${row.name}】吗？`, '复制规则', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'info'
      }).then(async () => {
        try {
          const form = this.mapRuleToForm(row)
          form.name = form.name + ' (副本)'
          form.code = 'RULE_' + Date.now().toString().slice(-8)
          await createAutomationRule(form)
          this.$message.success('规则复制成功')
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    handleTrigger(row) {
      this.$confirm(`确定要手动触发规则【${row.name}】吗？此操作可能影响匹配的订单。`, '手动触发', {
        confirmButtonText: '执行',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        const loading = this.$loading({
          lock: true,
          text: '正在执行规则...',
          spinner: 'el-icon-loading',
          background: 'rgba(0, 0, 0, 0.7)'
        })
        try {
          const res = await triggerAutomationRule(row.id)
          const data = res.data
          loading.close()
          if (data.success !== false) {
            const d = data.data || {}
            this.$message.success(`规则执行完成，共匹配 ${d.matched_count || 0} 个订单，成功处理 ${d.success_count || 0} 个`)
          } else {
            this.$message.error(data.message || '规则执行失败')
          }
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          loading.close()
          console.error(e)
        }
      }).catch(() => {})
    },
    handleDelete(row) {
      this.$confirm(`确定要删除规则【${row.name}】吗？删除后将无法恢复！`, '删除确认', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'error'
      }).then(async () => {
        try {
          await deleteAutomationRule(row.id)
          this.$message.success('删除成功')
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    async handleToggleStatus(row) {
      try {
        await toggleAutomationRuleEnabled(row.id)
        this.$message.success(row.isEnabled ? `规则【${row.name}】已启用` : `规则【${row.name}】已禁用`)
        this.fetchList()
        this.fetchStats()
      } catch (e) {
        row.isEnabled = !row.isEnabled
        console.error(e)
      }
    },
    addConditionGroup() {
      this.formData.conditions.push({
        logic: 'AND',
        rules: [{ field: '', operator: '', value: '' }]
      })
    },
    removeConditionGroup(groupIndex) {
      this.formData.conditions.splice(groupIndex, 1)
    },
    addCondition(groupIndex) {
      this.formData.conditions[groupIndex].rules.push({
        field: '', operator: '', value: ''
      })
    },
    removeCondition(groupIndex, conditionIndex) {
      this.formData.conditions[groupIndex].rules.splice(conditionIndex, 1)
    },
    handleSubmit() {
      this.$refs.ruleForm.validate(async (valid) => {
        if (!valid) return
        this.submitLoading = true
        try {
          const payload = {
            name: this.formData.name,
            code: this.formData.code,
            type: this.formData.type,
            description: this.formData.description,
            priority: this.formData.priority,
            conditions: {
              logic: this.formData.conditionLogic,
              groups: this.formData.conditions
            },
            actions: this.formData.actions,
            warehouse_id: this.formData.warehouseId,
            country_code: this.formData.countryCode,
            source_channel: this.formData.sourceChannel,
            min_amount: this.formData.minAmount,
            max_amount: this.formData.maxAmount,
            active_time_start: this.formData.activeTimeStart,
            active_time_end: this.formData.activeTimeEnd,
            weekdays: this.formData.weekdays,
            is_enabled: this.formData.isEnabled,
            stop_chain: this.formData.stopChain
          }
          if (this.isEditMode && this.editingId) {
            await updateAutomationRule(this.editingId, payload)
          } else {
            await createAutomationRule(payload)
          }
          this.$message.success(this.isEditMode ? '规则编辑成功' : '规则创建成功')
          this.formDialogVisible = false
          this.fetchList()
          this.fetchStats()
        } catch (e) {
          console.error(e)
        } finally {
          this.submitLoading = false
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.automation-rule-list {
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
      width: 52px;
      height: 52px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 14px;
      font-size: 26px;
      color: #fff;
    }

    .stat-total .stat-icon {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .stat-success .stat-icon {
      background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
    }
    .stat-today .stat-icon {
      background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
    }

    .stat-value {
      font-size: 22px;
      font-weight: 600;
      color: #303133;
    }

    .stat-label {
      font-size: 13px;
      color: #909399;
      margin-top: 4px;
    }
  }

  .page-card {
    .page-toolbar {
      margin-bottom: 16px;
      display: flex;
      justify-content: flex-end;
    }

    .rule-tabs {
      margin-bottom: 16px;
    }

    .muted {
      color: #909399;
    }

    .text-xs {
      font-size: 12px;
    }

    .float-right {
      float: right;
    }

    .rule-code {
      font-size: 11px;
      margin-top: 2px;
    }

    .priority-badge {
      ::v-deep .el-badge__content {
        background-color: #409EFF;
        border: none;
      }
    }

    .weekdays {
      margin-top: 2px;
    }

    .pagination {
      margin-top: 16px;
      text-align: right;
    }
  }

  .rule-form-dialog {
    ::v-deep .el-form-item {
      margin-bottom: 16px;
    }

    .divider-title {
      font-size: 14px;
      font-weight: 500;
      color: #303133;
    }

    .condition-editor {
      background: #fafafa;
      border-radius: 6px;
      padding: 16px;
      border: 1px solid #ebeef5;

      .group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px dashed #dcdfe6;
      }

      .group-body {
        background: #fff;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 12px;
        border: 1px solid #ebeef5;

        &:last-child {
          margin-bottom: 0;
        }
      }

      .group-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 500;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px dashed #ebeef5;
      }

      .condition-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;

        &:last-child {
          margin-bottom: 0;
        }
      }

      .condition-footer {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #ebeef5;
      }
    }

    .block {
      display: block;
    }

    .mt-4 {
      margin-top: 4px;
    }
  }
}
</style>
