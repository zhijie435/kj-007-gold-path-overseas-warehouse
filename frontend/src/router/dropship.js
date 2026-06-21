export const routes = [
  {
    path: '/dropship/orders',
    name: 'DropshipOrderList',
    component: () => import('@/views/OverseaDropship/List.vue'),
    meta: { title: '一件代发订单管理', requiresAuth: true }
  },
  {
    path: '/dropship/orders/create',
    name: 'DropshipOrderCreate',
    component: () => import('@/views/OverseaDropship/Create.vue'),
    meta: { title: '创建代发单', requiresAuth: true }
  },
  {
    path: '/dropship/orders/:id',
    name: 'DropshipOrderDetail',
    component: () => import('@/views/OverseaDropship/Detail.vue'),
    meta: { title: '代发单详情', requiresAuth: true }
  },
  {
    path: '/dropship/warehouse-configs',
    name: 'WarehouseConfigList',
    component: () => import('@/views/OverseaWarehouse/List.vue'),
    meta: { title: '海外仓配置', requiresAuth: true }
  },
  {
    path: '/dropship/automation-rules',
    name: 'AutomationRuleList',
    component: () => import('@/views/AutomationRule/List.vue'),
    meta: { title: '自动化规则', requiresAuth: true }
  },
  {
    path: '/dropship/automation-rules/create',
    name: 'AutomationRuleCreate',
    component: () => import('@/views/AutomationRule/Create.vue'),
    meta: { title: '创建规则', requiresAuth: true }
  },
  {
    path: '/dropship/automation-rules/:id',
    name: 'AutomationRuleEdit',
    component: () => import('@/views/AutomationRule/Create.vue'),
    meta: { title: '编辑规则', requiresAuth: true }
  },
  {
    path: '/dropship/wms-callbacks',
    name: 'WmsCallbackLogs',
    component: () => import('@/views/OverseaWarehouse/CallbackLogs.vue'),
    meta: { title: 'WMS回调日志', requiresAuth: true }
  }
]

export default routes
