import request from '@/utils/request'

export function getWmsCallbackLogs(params) {
  return request({
    url: '/wms-callback-logs',
    method: 'get',
    params
  })
}

export function getWmsCallbackLog(id) {
  return request({
    url: `/wms-callback-logs/${id}`,
    method: 'get'
  })
}

export function retryWmsCallbackLog(id) {
  return request({
    url: `/wms-callback-logs/${id}/retry`,
    method: 'post'
  })
}

export function getWmsCallbackStatistics() {
  return request({
    url: '/wms-callback-logs/statistics',
    method: 'get'
  })
}
