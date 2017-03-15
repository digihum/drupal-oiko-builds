/*
 * Redux actions.
 *
 * These are the actions that can be dispatched on our state object.
 */


export const SET_MAP_STATE = 'oiko/SET_MAP_STATE';
export function setMapState(level, lat, lng) {
  return { type: SET_MAP_STATE,
    level: level,
    lat: parseFloat(lat).toFixed(2),
    lng: parseFloat(lng).toFixed(2)
  }
}

export const SET_TIME_BROWSER_STATE = 'oiko/SET_TIME_BROWSER_STATE';
export function setTimeBrowserState(current, start, end) {
  return { type: SET_TIME_BROWSER_STATE,
    current,
    start,
    end
  }
}

export const SET_VISUALISATION = 'oiko/SET_VISUALISATION';
export function setVisualisation(view = 'map') {
  return { type: SET_VISUALISATION, view }
}

export const ADD_APP_MODULE = 'oiko/ADD_APP_MODULE';
export function addAppModule(name) {
  return { type: ADD_APP_MODULE, name }
}

export const APP_MODULE_DONE_LOADING = 'oiko/APP_MODULE_DONE_LOADING';
export function appModuleDoneLoading(name) {
  return { type: APP_MODULE_DONE_LOADING, name }
}
