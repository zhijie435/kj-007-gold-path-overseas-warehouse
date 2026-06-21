<template>
  <div class="dashboard">
    <el-row :gutter="16" class="stat-cards">
      <el-col :xs="12" :sm="8" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon orders">
              <i class="el-icon-s-order"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.totalOrders }}</div>
              <div class="stat-label">总订单数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon pending">
              <i class="el-icon-time"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.pendingOrders }}</div>
              <div class="stat-label">待处理</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon dropship">
              <i class="el-icon-s-promotion"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.dropshipOrders }}</div>
              <div class="stat-label">代发订单</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon warehouses">
              <i class="el-icon-office-building"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.warehouses }}</div>
              <div class="stat-label">海外仓</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="16">
        <el-card shadow="never">
          <div slot="header">快速入口</div>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-card shadow="hover" class="quick-card" @click.native="$router.push('/dropship/orders')">
                <i class="el-icon-s-promotion quick-icon"></i>
                <div>代发订单管理</div>
              </el-card>
            </el-col>
            <el-col :span="8">
              <el-card shadow="hover" class="quick-card" @click.native="$router.push('/dropship/warehouse-configs')">
                <i class="el-icon-office-building quick-icon"></i>
                <div>海外仓配置</div>
              </el-card>
            </el-col>
            <el-col :span="8">
              <el-card shadow="hover" class="quick-card" @click.native="$router.push('/dropship/automation-rules')">
                <i class="el-icon-s-tools quick-icon"></i>
                <div>自动化规则</div>
              </el-card>
            </el-col>
          </el-row>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never">
          <div slot="header">待办事项</div>
          <div class="todo-list">
            <div class="todo-item">
              <el-tag type="warning" size="small">待审核</el-tag>
              <span>{{ stats.pendingReview }} 笔代发单待审核</span>
            </div>
            <div class="todo-item">
              <el-tag type="primary" size="small">待推送</el-tag>
              <span>{{ stats.pendingPush }} 笔代发单待推送</span>
            </div>
            <div class="todo-item">
              <el-tag type="danger" size="small">异常</el-tag>
              <span>{{ stats.exception }} 笔代发单异常</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
export default {
  name: 'Dashboard',
  data() {
    return {
      stats: {
        totalOrders: 1286,
        pendingOrders: 38,
        dropshipOrders: 86,
        warehouses: 5,
        pendingReview: 23,
        pendingPush: 15,
        exception: 5
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.dashboard {
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

      &.orders { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
      &.pending { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
      &.dropship { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
      &.warehouses { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); }
    }

    .stat-value {
      font-size: 24px;
      font-weight: 600;
      color: #303133;
    }

    .stat-label {
      font-size: 13px;
      color: #909399;
      margin-top: 4px;
    }
  }

  .quick-card {
    text-align: center;
    cursor: pointer;
    padding: 16px 0;
    transition: all 0.3s;

    &:hover {
      transform: translateY(-2px);
    }

    .quick-icon {
      font-size: 32px;
      color: #409EFF;
      margin-bottom: 8px;
    }
  }

  .todo-list {
    .todo-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid #ebeef5;
      font-size: 14px;
      color: #606266;

      &:last-child {
        border-bottom: none;
      }
    }
  }
}
</style>
