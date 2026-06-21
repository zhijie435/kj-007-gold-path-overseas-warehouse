<template>
  <div class="not-found">
    <div class="content">
      <h1>{{ errorCode }}</h1>
      <p>{{ errorMessage }}</p>
      <el-button type="primary" @click="$router.push('/')">返回首页</el-button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'NotFound',
  computed: {
    errorCode() {
      return this.$route.path === '/403' ? '403' : '404'
    },
    errorMessage() {
      return this.$route.path === '/403' ? '抱歉，您没有权限访问此页面' : '抱歉，您访问的页面不存在'
    }
  }
}
</script>

<style lang="scss" scoped>
.not-found {
  height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;

  .content {
    text-align: center;

    h1 {
      font-size: 72px;
      color: #409EFF;
      margin: 0 0 16px;
    }

    p {
      font-size: 18px;
      color: #909399;
      margin: 0 0 32px;
    }
  }
}
</style>
