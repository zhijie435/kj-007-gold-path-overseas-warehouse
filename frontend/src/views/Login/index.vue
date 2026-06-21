<template>
  <div class="login-container">
    <el-card class="login-card" shadow="always">
      <div class="login-header">
        <h2>电商订单库存后台</h2>
        <p>请登录您的账户</p>
      </div>
      <el-form ref="loginForm" :model="loginForm" :rules="loginRules" label-width="0">
        <el-form-item prop="email">
          <el-input
            v-model="loginForm.email"
            prefix-icon="el-icon-user"
            placeholder="请输入邮箱"
          />
        </el-form-item>
        <el-form-item prop="password">
          <el-input
            v-model="loginForm.password"
            prefix-icon="el-icon-lock"
            type="password"
            placeholder="请输入密码"
            show-password
            @keyup.enter.native="handleLogin"
          />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            :loading="loading"
            style="width: 100%"
            @click="handleLogin"
          >
            登录
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'Login',
  data() {
    return {
      loading: false,
      loginForm: {
        email: '',
        password: ''
      },
      loginRules: {
        email: [{ required: true, message: '请输入邮箱', trigger: 'blur' }],
        password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
      }
    }
  },
  methods: {
    handleLogin() {
      this.$refs.loginForm.validate(async (valid) => {
        if (!valid) return
        this.loading = true
        try {
          await this.$store.dispatch('login', this.loginForm)
          this.$message.success('登录成功')
          const redirect = this.$route.query.redirect || '/'
          this.$router.push(redirect)
        } catch (e) {
          this.$message.error(e.response?.data?.message || '登录失败')
        } finally {
          this.loading = false
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.login-container {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
  width: 420px;
  border-radius: 12px;

  .login-header {
    text-align: center;
    margin-bottom: 32px;

    h2 {
      font-size: 24px;
      color: #303133;
      margin: 0 0 8px;
    }

    p {
      font-size: 14px;
      color: #909399;
    }
  }
}
</style>
