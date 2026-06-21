export function createMockMessage() {
  const fn = jest.fn()
  fn.success = jest.fn()
  fn.warning = jest.fn()
  fn.error = jest.fn()
  fn.info = jest.fn()
  return fn
}

export function createMockRouter() {
  return {
    push: jest.fn(),
    back: jest.fn()
  }
}

export function createMockConfirm(resolveValue = true) {
  if (resolveValue) {
    return jest.fn(() => Promise.resolve())
  }
  return jest.fn(() => Promise.reject(new Error('cancelled')))
}
