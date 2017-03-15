import queryString from 'query-string';

export function changeQueryString(state, changes = {}, replace = true) {
  let parsed = queryString.parse(state.search);
  parsed = Object.assign({}, parsed, changes);
  return Object.assign({}, state, {action: replace ? 'REPLACE' : 'PUSH', search : queryString.stringify(parsed)});
}

export function fetchQueryStringElements(state, key, _default = null) {
  let parsed = queryString.parse(state.search);
  return typeof parsed[key] !== 'undefined' ? parsed[key] : _default;
}