import { UPDATE_LOCATION } from './vendor/redux-history';
import { fetchQueryStringElements } from './plumbing/querystring-helpers';

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

export const REQUEST_CIDOC_ENTITY_FAILURE = 'oiko/REQUEST_CIDOC_ENTITY_FAILURE';
/**
 * An action that will indicate that the given CIDOC entity has failed to load.
 *
 * @param id
 *   The CIDOC entity that failed to load.
 *
 * @returns {{type: string, id: *, failedAt: number}}
 */
function receiveCidocEntityFailure(id) {
  return {
    type: REQUEST_CIDOC_ENTITY_FAILURE,
    id,
    failedAt: Date.now()
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
    Drupal.oiko.displayLoadingContentInLeafletSidebar();
    return Drupal.oiko.displayContentInLeafletSidebar(id, () => {
      // Dispatch our receieveCidocEntity action on the store.
      dispatch(receiveCidocEntity(id));
    }, () => {
      Drupal.oiko.displayFailureContentInLeafletSidebar();
      dispatch(receiveCidocEntityFailure(id));
    });
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
    case REQUEST_CIDOC_ENTITY_FAILURE:
      return Object.assign({}, state, {
        isFetching: false,
        lastUpdated: action.failedAt,
        id: null
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
    case REQUEST_CIDOC_ENTITY_FAILURE:
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
