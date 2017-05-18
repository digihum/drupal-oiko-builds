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
    // Special handling for a simple array.
    if (isSimpleArrayOfStrings(structuredChanges[key])) {
      changes[key] = 's' + structuredChanges[key].join(',');
    }
    else {
      changes[key] = JSON.stringify(structuredChanges[key]);
    }
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
    if (element.charAt(0) === 's') {
      // Special handling for a simple array.
      return element.substring(1).split(',');
    }
    else {
      return JSON.parse(element);
    }
  }
  else {
    return _default;
  }
}

/**
 * Determine if the given varuable is an array of scalars.
 *
 * @param variable
 * @returns {boolean}
 */
function isSimpleArrayOfStrings(variable) {
  let scalars = false;
  if (Array.isArray(variable)) {
    scalars = true;
    for (let i = 0;i < variable.length; i++) {
      const type = typeof variable[i];
      if (type !== 'string' && type !== 'number') {
        scalars = false;
        break;
      }
    }
  }
  return scalars;
}
