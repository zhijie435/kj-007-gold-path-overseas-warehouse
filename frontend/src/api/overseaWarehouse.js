import request from '@/utils/request'

export function getWarehouseConfigs(params) {
  return request({
    url: '/warehouse-configs',
    method: 'get',
    params
  })
}

export function getWarehouseConfig(id) {
  return request({
    url: `/warehouse-configs/${id}`,
    method: 'get'
  })
}

export function createWarehouseConfig(data) {
  return request({
    url: '/warehouse-configs',
    method: 'post',
    data
  })
}

export function updateWarehouseConfig(id, data) {
  return request({
    url: `/warehouse-configs/${id}`,
    method: 'put',
    data
  })
}

export function deleteWarehouseConfig(id) {
  return request({
    url: `/warehouse-configs/${id}`,
    method: 'delete'
  })
}

export function toggleWarehouseStatus(id) {
  return request({
    url: `/warehouse-configs/${id}/toggle-status`,
    method: 'post'
  })
}

export function testWarehouseConnection(id) {
  return request({
    url: `/warehouse-configs/${id}/test-connection`,
    method: 'post'
  })
}

export function syncWarehouseInventory(id) {
  return request({
    url: `/warehouse-configs/${id}/sync-inventory`,
    method: 'post'
  })
}

export function syncWarehouseTracking(id) {
  return request({
    url: `/warehouse-configs/${id}/sync-tracking`,
    method: 'post'
  })
}

export function getWarehouseStatusOptions() {
  return request({
    url: '/warehouse-configs/status-options',
    method: 'get'
  })
}

export function getWarehouseProviderOptions() {
  return request({
    url: '/warehouse-configs/provider-options',
    method: 'get'
  })
}
