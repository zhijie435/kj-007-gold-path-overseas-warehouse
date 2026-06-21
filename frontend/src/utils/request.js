import axios from 'axios'
import store from '@/store'
import router from '@/router'
import { MessageBox, Message } from 'element-ui'

const service = axios.create({
  baseURL: import.meta.env.VITE_APP_API_BASE_URL || '/api/v1',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json'
  }
})

service.interceptors.request.use(
  config => {
    const token = store.state.token
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  error => Promise.reject(error)
)

let isRefreshing = false

service.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {
      if (!isRefreshing) {
        isRefreshing = true
        MessageBox.confirm('登录状态已过期，请重新登录', '提示', {
          confirmButtonText: '重新登录',
          cancelButtonText: '取消',
          type: 'warning',
          closeOnClickModal: false
        }).then(() => {
          store.dispatch('logout')
          router.push('/login')
        }).catch(() => {}).finally(() => {
          isRefreshing = false
        })
      }
      return Promise.reject(error)
    }

    const message = error.response?.data?.message || error.message || '网络错误'
    Message({ message, type: 'error', duration: 3000 })
    return Promise.reject(error)
  }
)

export default service
