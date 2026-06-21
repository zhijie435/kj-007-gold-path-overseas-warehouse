import Vue from 'vue'
import Vuex from 'vuex'
import { login, logout, getUserInfo } from '@/api/auth'

Vue.use(Vuex)

const AUTH_KEYS = ['token', 'userInfo', 'roles', 'permissions']

function clearAuthStorage() {
  AUTH_KEYS.forEach(key => localStorage.removeItem(key))
}

function setStorage(key, value) {
  localStorage.setItem(key, typeof value === 'object' ? JSON.stringify(value) : value)
}

export default new Vuex.Store({
  state: {
    token: localStorage.getItem('token') || '',
    userInfo: JSON.parse(localStorage.getItem('userInfo') || 'null'),
    roles: JSON.parse(localStorage.getItem('roles') || '[]'),
    permissions: JSON.parse(localStorage.getItem('permissions') || '[]')
  },
  getters: {
    isLogin: state => !!state.token,
    userInfo: state => state.userInfo,
    roles: state => state.roles,
    permissions: state => {
      const perms = new Set()
      state.permissions.forEach(p => perms.add(p.name || p))
      state.roles.forEach(role => {
        if (role.permissions) {
          role.permissions.forEach(p => perms.add(p.name || p))
        }
      })
      return Array.from(perms)
    },
    hasPermission: (state, getters) => perm => {
      return getters.permissions.includes(perm) || getters.permissions.includes('*')
    }
  },
  mutations: {
    SET_TOKEN(state, token) {
      state.token = token
      setStorage('token', token)
    },
    SET_USER_INFO(state, userInfo) {
      state.userInfo = userInfo
      setStorage('userInfo', userInfo)
    },
    SET_ROLES(state, roles) {
      state.roles = roles
      setStorage('roles', roles)
    },
    SET_PERMISSIONS(state, permissions) {
      state.permissions = permissions
      setStorage('permissions', permissions)
    },
    CLEAR_AUTH(state) {
      state.token = ''
      state.userInfo = null
      state.roles = []
      state.permissions = []
      clearAuthStorage()
    }
  },
  actions: {
    async login({ commit }, loginData) {
      const res = await login(loginData)
      commit('SET_TOKEN', res.data.token)
      commit('SET_USER_INFO', res.data.user)
      commit('SET_ROLES', res.data.user.roles || [])
      commit('SET_PERMISSIONS', res.data.user.permissions || [])
      return res.data
    },
    async logout({ commit }) {
      try {
        await logout()
      } catch (e) {
        // ignore
      }
      commit('CLEAR_AUTH')
    },
    async getUserInfo({ commit, state }) {
      if (!state.token) return null
      try {
        const res = await getUserInfo()
        commit('SET_USER_INFO', res.data)
        commit('SET_ROLES', res.data.roles || [])
        commit('SET_PERMISSIONS', res.data.permissions || [])
        return res.data
      } catch (e) {
        if (e.response?.status === 401) {
          commit('CLEAR_AUTH')
        }
        throw e
      }
    }
  }
})
