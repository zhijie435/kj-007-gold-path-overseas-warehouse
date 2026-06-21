<template>
  <div class="rule-create">
    <div class="page-header">
      <div class="header-left">
        <el-breadcrumb separator-class="el-icon-arrow-right" class="breadcrumb">
          <el-breadcrumb-item :to="{ path: '/dropship/automation-rules' }">自动化规则</el-breadcrumb-item>
          <el-breadcrumb-item>创建规则</el-breadcrumb-item>
        </el-breadcrumb>
        <h2 class="page-title">{{ isEdit ? '编辑自动化规则' : '创建自动化规则' }}</h2>
      </div>
      <div class="header-right">
        <el-button @click="$router.back()">
          <i class="el-icon-back"></i> 返回
        </el-button>
        <el-button @click="handleReset">
          <i class="el-icon-refresh"></i> 重置
        </el-button>
        <el-button type="primary" :loading="saving" @click="handleSubmit">
          <i class="el-icon-check"></i> {{ isEdit ? '保存修改' : '创建规则' }}
        </el-button>
      </div>
    </div>

    <el-card class="form-card">
      <el-form
        :model="form"
        :rules="rules"
        ref="ruleFormRef"
        label-width="120px"
        :label-position="'right'"
      >
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="规则名称" prop="name">
              <el-input v-model="form.name" maxlength="200" show-word-limit placeholder="请输入规则名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="规则编码" prop="code">
              <el-input v-model="form.code" maxlength="100" placeholder="英文+下划线，唯一标识" :disabled="isEdit" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="规则类型" prop="type">
              <el-select v-model="form.type" placeholder="请选择规则类型" style="width: 100%" @change="handleTypeChange">
                <el-option-group
                  v-for="(items, category) in groupedTypeOptions"
                  :key="category"
                  :label="category"
                >
                  <el-option
                    v-for="opt in items"
                    :key="opt.value"
                    :label="opt.label"
                    :value="opt.value"
                  >
                    <span>{{ opt.label }}</span>
                    <span style="float:right;color:#8492a6;font-size:12px">{{ opt.description }}</span>
                  </el-option>
                </el-option-group>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="执行优先级" prop="priority">
              <el-input-number
                v-model="form.priority"
                :min="0"
                :max="999"
                :step="1"
                style="width: 100%"
              />
              <div class="form-tip">数字越大越先执行，相同值按创建顺序</div>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="规则描述">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="2"
            maxlength="500"
            show-word-limit
            placeholder="请简要描述此规则的用途"
          />
        </el-form-item>

        <el-divider content-position="left">
          <span class="divider-title">
            <i class="el-icon-setting"></i> 基本条件
          </span>
        </el-divider>

        <el-row :gutter="24">
          <el-col :span="8">
            <el-form-item label="绑定仓库">
              <el-select v-model="form.warehouse_id" placeholder="全局适用" clearable filterable style="width: 100%">
                <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="适用国家">
              <el-input v-model="form.country_code" placeholder="例如: US, GB" maxlength="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="适用渠道">
              <el-select v-model="form.source_channel" placeholder="全部渠道" clearable style="width: 100%">
                <el-option label="手动创建" value="manual" />
                <el-option label="Shopify" value="shopify" />
                <el-option label="Amazon" value="amazon" />
                <el-option label="TikTok Shop" value="tiktok" />
                <el-option label="Shopee" value="shopee" />
                <el-option label="Lazada" value="lazada" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="24">
          <el-col :span="8">
            <el-form-item label="最小订单额">
              <el-input-number v-model="form.min_order_amount" :min="0" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="最大订单额">
              <el-input-number v-model="form.max_order_amount" :min="0" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="生效星期">
              <el-checkbox-group v-model="form.weekdays">
                <el-checkbox :label="1">一</el-checkbox>
                <el-checkbox :label="2">二</el-checkbox>
                <el-checkbox :label="3">三</el-checkbox>
                <el-checkbox :label="4">四</el-checkbox>
                <el-checkbox :label="5">五</el-checkbox>
                <el-checkbox :label="6">六</el-checkbox>
                <el-checkbox :label="7">日</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="生效时段">
              <el-time-picker
                is-range
                v-model="timeRange"
                range-separator="至"
                start-placeholder="开始时间"
                end-placeholder="结束时间"
                format="HH:mm"
                value-format="HH:mm:ss"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="命中后中止">
              <el-switch v-model="form.stop_chain" active-text="命中后停止后续规则" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">
          <span class="divider-title">
            <i class="el-icon-filter"></i> 高级触发条件
          </span>
          <el-button type="text" size="small" class="add-cond-btn" @click="addConditionGroup">
            <i class="el-icon-plus"></i> 添加条件组
          </el-button>
        </el-divider>

        <div class="conditions-builder" v-if="form.conditions?.groups?.length">
          <div
            v-for="(group, gi) in form.conditions.groups"
            :key="gi"
            class="condition-group"
          >
            <div class="group-header">
              <span>条件组 {{ gi + 1 }}</span>
              <el-radio-group
                v-model="group.logic"
                size="mini"
                style="margin: 0 12px"
              >
                <el-radio-button label="AND">全部满足(AND)</el-radio-button>
                <el-radio-button label="OR">任一满足(OR)</el-radio-button>
              </el-radio-group>
              <el-button type="text" size="mini" @click="addCondition(gi)">+ 加条件</el-button>
              <el-button
                type="text"
                size="mini"
                style="color: #f56c6c; margin-left: auto"
                @click="removeGroup(gi)"
              >删除组</el-button>
            </div>
            <div
              v-for="(cond, ci) in group.conditions"
              :key="ci"
              class="condition-row"
            >
              <el-select v-model="cond.field" placeholder="字段" size="small" style="width: 180px">
                <el-option label="收货国家" value="receiver_country" />
                <el-option label="订单金额" value="total_cost" />
                <el-option label="商品件数" value="total_items" />
                <el-option label="订单重量" value="weight" />
                <el-option label="物流渠道" value="shipping_method_code" />
                <el-option label="指定SKU" value="sku" />
                <el-option label="来源渠道" value="source_channel" />
                <el-option label="收件人邮箱后缀" value="email_domain" />
                <el-option label="邮编区域" value="postal_code" />
              </el-select>
              <el-select v-model="cond.operator" placeholder="运算符" size="small" style="width: 140px">
                <el-option label="等于 ==" value="eq" />
                <el-option label="不等于 !=" value="ne" />
                <el-option label="大于 >" value="gt" />
                <el-option label="大于等于 >=" value="gte" />
                <el-option label="小于 <" value="lt" />
                <el-option label="小于等于 <=" value="lte" />
                <el-option label="包含 contains" value="contains" />
                <el-option label="不包含 not_contains" value="not_contains" />
                <el-option label="在范围内 between" value="between" />
                <el-option label="属于任一 in" value="in" />
                <el-option label="开头为 starts_with" value="starts_with" />
                <el-option label="为空 empty" value="empty" />
              </el-select>
              <el-input
                v-model="cond.value"
                placeholder="值 (多个用逗号分隔)"
                size="small"
                style="width: 240px; flex: 1"
              />
              <el-button type="text" size="mini" style="color: #f56c6c" @click="removeCondition(gi, ci)">
                删除
              </el-button>
            </div>
          </div>
        </div>
        <el-empty v-else description="暂无高级条件，规则将基于基本条件执行" :image-size="80" />

        <el-divider content-position="left">
          <span class="divider-title">
            <i class="el-icon-video-play"></i> 执行动作配置
          </span>
        </el-divider>

        <el-card shadow="never" class="action-config-card">
          <template v-if="form.type === 'auto_review'">
            <el-form-item label="审核方式">
              <el-radio-group v-model="actionConfig.review_action">
                <el-radio label="auto_pass">自动通过</el-radio>
                <el-radio label="require_human">需要人工二次确认</el-radio>
                <el-radio label="flag_review">标记为重点审核</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="最大自动审核金额">
              <el-input-number v-model="actionConfig.max_auto_amount" :min="0" :precision="2" :step="50" />
            </el-form-item>
          </template>

          <template v-else-if="form.type === 'auto_assign_warehouse'">
            <el-form-item label="分仓策略">
              <el-radio-group v-model="actionConfig.warehouse_strategy">
                <el-radio label="nearest">最近距离</el-radio>
                <el-radio label="cheapest">最低运费</el-radio>
                <el-radio label="fastest">最快时效</el-radio>
                <el-radio label="stock_priority">库存优先</el-radio>
                <el-radio label="round_robin">轮询分配</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="备选仓库ID(逗号分隔)">
              <el-input v-model="actionConfig.fallback_warehouses" placeholder="例如: 2,5,8" />
            </el-form-item>
          </template>

          <template v-else-if="form.type === 'auto_assign_shipping'">
            <el-form-item label="分配策略">
              <el-radio-group v-model="actionConfig.shipping_strategy">
                <el-radio label="cheapest">最低价</el-radio>
                <el-radio label="fastest">最快时效</el-radio>
                <el-radio label="balance">性价比最优</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="指定物流渠道代码">
              <el-input v-model="actionConfig.force_shipping_method" placeholder="不填则按策略匹配" />
            </el-form-item>
          </template>

          <template v-else-if="form.type === 'auto_push_wms'">
            <el-form-item label="推送方式">
              <el-radio-group v-model="actionConfig.push_mode">
                <el-radio label="realtime">实时推送(立即)</el-radio>
                <el-radio label="delayed">延迟推送</el-radio>
                <el-radio label="batch">定时批量推送</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="延迟时间(秒)" v-if="actionConfig.push_mode === 'delayed'">
              <el-input-number v-model="actionConfig.delay_seconds" :min="1" :step="60" />
            </el-form-item>
            <el-form-item label="失败重试次数">
              <el-input-number v-model="actionConfig.max_retries" :min="0" :max="10" />
            </el-form-item>
          </template>

          <template v-else-if="form.type === 'auto_cancel_order'">
            <el-form-item label="超时时间(小时)">
              <el-input-number v-model="actionConfig.timeout_hours" :min="1" :step="1" />
            </el-form-item>
            <el-form-item label="触发的状态">
              <el-checkbox-group v-model="actionConfig.trigger_statuses">
                <el-checkbox label="pending_review">待审核</el-checkbox>
                <el-checkbox label="push_failed">推单失败</el-checkbox>
                <el-checkbox label="processing">处理中</el-checkbox>
                <el-checkbox label="exception">异常</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </template>

          <template v-else-if="form.type === 'auto_sync_tracking'">
            <el-form-item label="同步频率(分钟)">
              <el-input-number v-model="actionConfig.interval_min" :min="15" :step="15" />
            </el-form-item>
            <el-form-item label="自动更新订单状态">
              <el-switch v-model="actionConfig.auto_update_status" />
            </el-form-item>
          </template>

          <template v-else-if="form.type === 'auto_sync_inventory'">
            <el-form-item label="同步频率(分钟)">
              <el-input-number v-model="actionConfig.interval_min" :min="30" :step="30" />
            </el-form-item>
            <el-form-item label="低于阈值时告警">
              <el-switch v-model="actionConfig.alert_on_low" />
            </el-form-item>
          </template>

          <template v-else-if="form.type === 'auto_notification'">
            <el-form-item label="通知渠道">
              <el-checkbox-group v-model="actionConfig.channels">
                <el-checkbox label="email">邮件</el-checkbox>
                <el-checkbox label="sms">短信</el-checkbox>
                <el-checkbox label="dingtalk">钉钉</el-checkbox>
                <el-checkbox label="wechat">企业微信</el-checkbox>
                <el-checkbox label="webhook">Webhook</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
            <el-form-item label="接收人(逗号分隔)">
              <el-input type="textarea" v-model="actionConfig.recipients" :rows="2" placeholder="邮箱/手机号/Webhook地址" />
            </el-form-item>
            <el-form-item label="触发事件">
              <el-checkbox-group v-model="actionConfig.events">
                <el-checkbox label="order_created">订单创建</el-checkbox>
                <el-checkbox label="order_pushed">订单推送WMS</el-checkbox>
                <el-checkbox label="order_shipped">订单已发货</el-checkbox>
                <el-checkbox label="order_delivered">订单签收</el-checkbox>
                <el-checkbox label="order_exception">订单异常</el-checkbox>
                <el-checkbox label="push_failed">推送失败</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </template>

          <template v-else>
            <el-alert
              title="选择规则类型后将显示对应的动作配置表单"
              type="info"
              :closable="false"
              show-icon
            />
          </template>
        </el-card>

        <el-divider />

        <el-row type="flex" justify="center">
          <el-col :span="12" style="text-align: center">
            <el-form-item label="启用规则" label-width="100px">
              <el-switch
                v-model="form.is_enabled"
                active-text="立即启用"
                inactive-text="保存为草稿"
              />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </el-card>
  </div>
</template>

<script>
import {
  getAutomationRuleTypeOptions,
  createAutomationRule,
  updateAutomationRule,
  getAutomationRule
} from '@/api/automationRule'
import { getWarehouseConfigs } from '@/api/overseaWarehouse'

export default {
  name: 'AutomationRuleCreate',
  props: {
    ruleId: { type: [String, Number], default: null }
  },
  data() {
    const validateCode = (rule, value, callback) => {
      if (!value) return callback(new Error('请输入规则编码'))
      if (!/^[a-zA-Z][a-zA-Z0-9_]{2,99}$/.test(value)) {
        return callback(new Error('编码以字母开头，支持字母数字下划线，3-100位'))
      }
      callback()
    }
    return {
      saving: false,
      isEdit: !!this.$route?.params?.id || !!this.ruleId,
      groupedTypeOptions: {},
      warehouseOptions: [],
      timeRange: [],
      form: this.getEmptyForm(),
      actionConfig: {},
      rules: {
        name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
        code: [{ required: true, validator: validateCode, trigger: 'blur' }],
        type: [{ required: true, message: '请选择规则类型', trigger: 'change' }],
        priority: [{ required: true, message: '请输入优先级', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.fetchTypeOptions()
    this.fetchWarehouses()
    if (this.isEdit) {
      this.loadRuleData()
    }
  },
  methods: {
    getEmptyForm() {
      return {
        id: null,
        name: '',
        code: '',
        type: '',
        description: '',
        priority: 50,
        warehouse_id: null,
        country_code: '',
        source_channel: '',
        min_order_amount: null,
        max_order_amount: null,
        weekdays: [],
        active_time_start: null,
        active_time_end: null,
        is_enabled: true,
        stop_chain: false,
        conditions: { groups: [] },
        actions: {}
      }
    },
    async fetchTypeOptions() {
      try {
        const res = await getAutomationRuleTypeOptions()
        this.groupedTypeOptions = res.data || {}
      } catch (e) {
        console.error(e)
      }
    },
    async fetchWarehouses() {
      try {
        const res = await getWarehouseConfigs({ per_page: 100, status: 'active' })
        this.warehouseOptions = res.data?.list || []
      } catch (e) {
        console.error(e)
      }
    },
    async loadRuleData() {
      const id = this.$route?.params?.id || this.ruleId
      try {
        const res = await getAutomationRule(id)
        const data = res.data || {}
        this.form = { ...this.getEmptyForm(), ...data }
        this.actionConfig = { ...(data.actions || {}) }
        if (data.active_time_start || data.active_time_end) {
          this.timeRange = [data.active_time_start || '00:00:00', data.active_time_end || '23:59:59']
        }
      } catch (e) {
        this.$message.error('加载规则失败')
      }
    },
    handleTypeChange() {
      this.actionConfig = {}
    },
    addConditionGroup() {
      if (!this.form.conditions) this.$set(this.form, 'conditions', { groups: [] })
      this.form.conditions.groups.push({
        logic: 'AND',
        conditions: [{ field: '', operator: '', value: '' }]
      })
    },
    removeGroup(gi) {
      this.form.conditions.groups.splice(gi, 1)
    },
    addCondition(gi) {
      this.form.conditions.groups[gi].conditions.push({ field: '', operator: '', value: '' })
    },
    removeCondition(gi, ci) {
      this.form.conditions.groups[gi].conditions.splice(ci, 1)
    },
    handleReset() {
      if (this.isEdit) {
        this.loadRuleData()
      } else {
        this.form = this.getEmptyForm()
        this.actionConfig = {}
        this.timeRange = []
      }
      this.$nextTick(() => this.$refs.ruleFormRef?.clearValidate())
    },
    async handleSubmit() {
      this.$refs.ruleFormRef.validate(async valid => {
        if (!valid) return
        const payload = { ...this.form }
        if (this.timeRange?.length === 2) {
          payload.active_time_start = this.timeRange[0]
          payload.active_time_end = this.timeRange[1]
        }
        payload.actions = { ...this.actionConfig }

        this.saving = true
        try {
          if (this.isEdit) {
            await updateAutomationRule(payload.id, payload)
            this.$message.success('规则修改成功')
          } else {
            await createAutomationRule(payload)
            this.$message.success('规则创建成功')
          }
          this.$router.push('/dropship/automation-rules')
        } catch (e) {
          console.error(e)
        } finally {
          this.saving = false
        }
      })
    }
  },
  watch: {
    '$route.params.id': {
      handler(v) {
        this.isEdit = !!v
        if (this.isEdit) this.loadRuleData()
      },
      immediate: false
    }
  }
}
</script>

<style lang="scss" scoped>
.rule-create {
  .breadcrumb { margin-bottom: 8px; }
  .page-title { margin: 4px 0 0; }
  .form-tip { font-size: 12px; color: #909399; margin-top: 4px; }
  .divider-title {
    font-size: 14px;
    font-weight: 600;
    i { margin-right: 4px; color: #409eff; }
  }
  .add-cond-btn { font-size: 12px; }
  .conditions-builder {
    padding: 4px 0;
    .condition-group {
      border: 1px solid #ebeef5;
      border-radius: 6px;
      padding: 12px 16px;
      margin-bottom: 12px;
      background: #fafbfc;
      .group-header {
        display: flex;
        align-items: center;
        font-size: 13px;
        font-weight: 600;
        color: #303133;
        margin-bottom: 10px;
      }
      .condition-row {
        display: flex;
        gap: 8px;
        align-items: center;
        padding: 6px 0;
        border-top: 1px dashed #ebeef5;
        &:first-of-type { border-top: none; }
      }
    }
  }
  .action-config-card {
    background: #fafcff;
    border: 1px dashed #d9ecff;
  }
  .form-card { padding: 24px 32px; }
}
</style>
