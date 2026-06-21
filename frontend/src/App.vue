<template>
  <el-container class="app-container">
    <el-header v-if="isLogin" class="app-header">
      <div class="header-left">
        <h1 class="logo">电商订单库存后台</h1>
      </div>
      <div class="header-right">
        <el-dropdown @command="handleCommand">
          <span class="user-info">
            <i class="el-icon-user"></i>
            {{ userInfo?.name || '用户' }}
            <i class="el-icon-arrow-down el-icon--right"></i>
          </span>
          <el-dropdown-menu slot="dropdown">
            <el-dropdown-item command="logout" divided>退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </el-dropdown>
      </div>
    </el-header>
    <el-container>
      <el-aside v-if="isLogin" width="220px" class="app-aside">
        <el-menu
          :default-active="$route.path"
          router
          background-color="#304156"
          text-color="#bfcbd9"
          active-text-color="#ffd04b"
        >
          <el-menu-item index="/dashboard">
            <i class="el-icon-s-home"></i>
            <span slot="title">数据概览</span>
          </el-menu-item>

          <el-submenu index="/dropship">
            <template slot="title">
              <i class="el-icon-s-promotion"></i>
              <span>海外仓一件代发</span>
            </template>
            <el-menu-item index="/dropship/orders">代发订单管理</el-menu-item>
            <el-menu-item index="/dropship/warehouse-configs">海外仓配置</el-menu-item>
            <el-menu-item index="/dropship/automation-rules">自动化规则</el-menu-item>
            <el-menu-item index="/dropship/wms-callbacks">WMS回调日志</el-menu-item>
          </el-submenu>
        </el-menu>
      </el-aside>
      <el-main class="app-main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script>
import { mapGetters } from 'vuex'

export default {
  name: 'App',
  computed: {
    ...mapGetters(['isLogin', 'userInfo'])
  },
  methods: {
    handleCommand(command) {
      if (command === 'logout') {
        this.$confirm('确定要退出登录吗？', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          this.$store.dispatch('logout')
          this.$router.push('/login')
          this.$message.success('已退出登录')
        }).catch(() => {})
      }
    }
  }
}
</script>

<style lang="scss">
.app-container {
  height: 100vh;
}

.app-header {
  background: #fff;
  border-bottom: 1px solid #e6e6e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 24px;

  .header-left {
    display: flex;
    align-items: center;
    gap: 12px;

    .logo {
      font-size: 18px;
      margin: 0;
      color: #303133;
    }
  }

  .header-right .user-info {
    cursor: pointer;
    color: #606266;
  }
}

.app-aside {
  background: #304156;
  overflow-x: hidden;

  .el-menu {
    border-right: none;
  }

  ::v-deep .el-submenu.is-active > .el-submenu__title {
    color: #ffd04b;

    i {
      color: #ffd04b;
    }
  }
}

.app-main {
  background: #f0f2f5;
  padding: 20px;
  overflow-y: auto;
}
</style>
