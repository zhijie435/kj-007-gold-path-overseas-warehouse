import request from '@/utils/request'

export function getDropshipOrders(params) {
  return request({
    url: '/dropship/orders',
    method: 'get',
    params
  })
}

export function getDropshipOrder(id) {
  return request({
    url: `/dropship/orders/${id}`,
    method: 'get'
  })
}

export function createDropshipOrder(data) {
  return request({
    url: '/dropship/orders',
    method: 'post',
    data
  })
}

export function updateDropshipOrder(id, data) {
  return request({
    url: `/dropship/orders/${id}`,
    method: 'put',
    data
  })
}

export function deleteDropshipOrder(id) {
  return request({
    url: `/dropship/orders/${id}`,
    method: 'delete'
  })
}

export function getDropshipStatistics(params) {
  return request({
    url: '/dropship/statistics',
    method: 'get',
    params
  })
}

export function getDropshipStatusOptions() {
  return request({
    url: '/dropship/status-options',
    method: 'get'
  })
}

export function getDropshipChannelOptions() {
  return request({
    url: '/dropship/channel-options',
    method: 'get'
  })
}

export function reviewDropshipOrder(id, data) {
  return request({
    url: `/dropship/orders/${id}/review`,
    method: 'post',
    data
  })
}

export function batchReviewDropshipOrders(data) {
  return request({
    url: '/dropship/batch-review',
    method: 'post',
    data
  })
}

export function pushDropshipOrder(id) {
  return request({
    url: `/dropship/orders/${id}/push`,
    method: 'post'
  })
}

export function batchPushDropshipOrders(data) {
  return request({
    url: '/dropship/batch-push',
    method: 'post',
    data
  })
}

export function retryPushDropshipOrder(id) {
  return request({
    url: `/dropship/orders/${id}/retry-push`,
    method: 'post'
  })
}

export function updateDropshipOrderStatus(id, data) {
  return request({
    url: `/dropship/orders/${id}/update-status`,
    method: 'post',
    data
  })
}

export function cancelDropshipOrder(id, data) {
  return request({
    url: `/dropship/orders/${id}/cancel`,
    method: 'post',
    data
  })
}

export function syncDropshipTracking(id) {
  return request({
    url: `/dropship/orders/${id}/sync-tracking`,
    method: 'post'
  })
}
