import { UPDATE_LOCATION } from './vendor/redux-history';
import { changeQueryString, fetchQueryStringElements } from './plumbing/querystring-helpers';

import { QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY } from './querystring-definitions';

/*
 * {
 *   id: CIDOC_ID,
 *   isFetching: true/false,
 *   lastUpdated: 1439478405547,
 * }
 */
export const REQUEST_CIDOC_ENTITY = 'oiko/REQUEST_CIDOC_ENTITY';
/**
 * Return an action that will initiate the request for the given CIDOC entity.
 *
 * @param id
 *   The CIDOC entity to load.
 * @returns {{type: string, id: *}}
 */
function requestCidocEntity(id) {
  return {
    type: REQUEST_CIDOC_ENTITY,
    id
  }
}

export const RECEIVE_CIDOC_ENTITY = 'oiko/RECEIVE_CIDOC_ENTITY';
/**
 * Return an action that will indicate that the given CIDOC entity has been loaded.
 *
 * @param id
 * The CIDOC entity that was just loaded.
 *
 * @returns {{type: string, id: *, receivedAt: number}}
 */
function receiveCidocEntity(id) {
  return {
    type: RECEIVE_CIDOC_ENTITY,
    id,
    receivedAt: Date.now()
  }
}

/**
 * Initial the fetch of a CIDOC entity.
 *
 * @param id
 *   The CIDOC entity to fetch.
 *
 * @returns {function(*)}
 */
function fetchCidocEntity(id) {
  return dispatch => {
    dispatch(requestCidocEntity(id));
    Drupal.oiko.sidebar.open('information');
    // Replace the content with the loading content.
    Drupal.oiko.displayLoadingContentInLeafletSidebar('');
    return Drupal.oiko.displayContentInLeafletSidebar(id, () => {
      // Dispatch our receieveCidocEntity action on the store.
      dispatch(receiveCidocEntity(id));
    }, () => {
      // @TODO: should we record the failure?
      // dispatch(receiveCidocEntity(id));
    });
  }
}

/**
 * Determine if we should fetch the specified CIDOC entity.
 *
 * @param state
 * @param id
 * @returns {boolean}
 */
function shouldFetchCidocEntity(state, id) {
  const cidocState = state.cidocEntity;
  if (!cidocState) {
    return true
  }
  else if (cidocState.isFetching) {
    return false
  }
  else {
    return cidocState.id != id;
  }
}

/**
 * Fetch the given CIDOC entity if we need to.
 *
 * @param id
 * @returns {function(*, *)}
 */
export function fetchFetchCidocEntityIfNeeded(id) {
  return (dispatch, getState) => {
    if (shouldFetchCidocEntity(getState(), id)) {
      // Dispatch a thunk from thunk!
      return dispatch(fetchCidocEntity(id))
    }
  }
}


function cidoc(state = {
  isFetching: false,
  id: 0
}, action) {
  switch (action.type) {
    case REQUEST_CIDOC_ENTITY:
      return Object.assign({}, state, {
        isFetching: true,
        id: action.id
      });
    case RECEIVE_CIDOC_ENTITY:
      return Object.assign({}, state, {
        isFetching: false,
        lastUpdated: action.receivedAt,
        id: action.id
      });
    default:
      return state
  }
}

/**
 * Reducer for the sidebar state-slice.
 *
 * @param state
 * @param action
 * @returns {*}
 */
export function cidocEntityReducer(state = {}, action) {
  switch (action.type) {
    case REQUEST_CIDOC_ENTITY:
    case RECEIVE_CIDOC_ENTITY:
      return Object.assign({}, state, cidoc(state, action));

    case UPDATE_LOCATION:
      return Object.assign({}, state, {
        isFetching: false,
        id: Number.parseInt(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY, state.id), 10)
      });

    default:
      return state;
  }
}

export function connectSidebar($, store) {

  let currentCidocEntity;
  let lastDispatch;

  const checkStoreState = () => {

    const {cidocEntity} = store.getState();

    if (cidocEntity.id && cidocEntity.id !== currentCidocEntity) {
      $(window).trigger('oikoSidebarOpen', cidocEntity.id);
    }

  };

  $(window).on('oiko.loaded', () => {
    checkStoreState();
    store.subscribe(checkStoreState);
  });

  $(window).bind('oikoSidebarOpen', function(e, id) {
    if (id !== currentCidocEntity) {
      currentCidocEntity = id;
      if (lastDispatch) {
        lastDispatch.abort();
      }
      lastDispatch = store.dispatch(fetchCidocEntity(id));
    }
  });
}
