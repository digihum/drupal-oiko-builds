/*
 * Portions of the query strings that are going to hold data.
 *
 * Each of these MUST be unique,
 * Changing these will break ALL existing inbound links, DANGER!
 *
 */

/**
 * The application view.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_VISUALISATION = 'view';

/**
 * The map zoom level.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_MAP_ZOOM = 'mz';

/**
 * The map center latitude.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_MAP_CENTER_LAT = 'mlat';

/**
 * The map center longitude.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_MAP_CENTER_LNG = 'mlng';

/**
 * The timeline browser current position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_BROWSER_POSITION = 'tbc';

/**
 * The timeline browser window start position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_BROWSER_START = 'tbs';

/**
 * The timeline browser window end position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_BROWSER_END = 'tbe';

/**
 * The comparative timeline window start position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINES_START = 'tls';

/**
 * The comparative timeline window end position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINES_END = 'tle';

/**
 * The cidoc entity being displayed in the sidebar.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY = 'id';

/**
 * The timelines being displayed in comparative timline viewer.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_ENTITIES = 'lin';

/**
 * The PHS categories being filtered.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_PHS_CATEGORIES = 'cat';

/**
 * The tags being filtered.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TAGS = 'tag';
