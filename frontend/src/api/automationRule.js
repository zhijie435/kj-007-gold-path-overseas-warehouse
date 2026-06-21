import request from '@/utils/request'

export function getAutomationRules(params) {
  return request({
    url: '/automation-rules',
    method: 'get',
    params
  })
}

export function getAutomationRule(id) {
  return request({
    url: `/automation-rules/${id}`,
    method: 'get'
  })
}

export function createAutomationRule(data) {
  return request({
    url: '/automation-rules',
    method: 'post',
    data
  })
}

export function updateAutomationRule(id, data) {
  return request({
    url: `/automation-rules/${id}`,
    method: 'put',
    data
  })
}

export function deleteAutomationRule(id) {
  return request({
    url: `/automation-rules/${id}`,
    method: 'delete'
  })
}

export function toggleAutomationRuleEnabled(id) {
  return request({
    url: `/automation-rules/${id}/toggle-enabled`,
    method: 'post'
  })
}

export function triggerAutomationRule(id) {
  return request({
    url: `/automation-rules/${id}/trigger`,
    method: 'post'
  })
}

export function getAutomationRuleTypeOptions() {
  return request({
    url: '/automation-rules/type-options',
    method: 'get'
  })
}

export function getAutomationStatistics() {
  return request({
    url: '/automation-rules/statistics',
    method: 'get'
  })
}
