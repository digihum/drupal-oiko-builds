import queryString from 'query-string';

export function changeQueryString(state, changes = {}, replace = true) {
  let parsed = queryString.parse(state.search);
  parsed = Object.assign({}, parsed, changes);
  return Object.assign({}, state, {action: replace ? 'REPLACE' : 'PUSH', search : queryString.stringify(parsed)});
}

export function changeQueryStringStructured(state, structuredChanges = {}, replace = true) {
  let changes = {};
  const keys = Object.keys(structuredChanges);
  for (let i in keys) {
    const key = keys[i];
    changes[key] = JSON.stringify(structuredChanges[key]);
  }
  return changeQueryString(state, changes, replace);
}

export function fetchQueryStringElements(state, key, _default = null) {
  const parsed = queryString.parse(state.search);
  return typeof parsed[key] !== 'undefined' ? parsed[key] : _default;
}

export function fetchQueryStringElementsStructured(state, key, _default = null) {
  const element = fetchQueryStringElements(state, key);
  if (element !== null) {
    return JSON.parse(element);
  }
  else {
    return _default;
  }
}