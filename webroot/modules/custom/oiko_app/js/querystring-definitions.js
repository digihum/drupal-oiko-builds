/*
 * Portions of the query strings that are going to hold data.
 *
 * Each of these MUST be unique,
 * Changing these will break ALL existing inbound links, DANGER!
 *
 * @TODO: Shorten all of these.
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
export const QUERYSTRING_VARIABLE_MAP_ZOOM = 'mapzoom';

/**
 * The map center latitude.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_MAP_CENTER_LAT = 'maplat';

/**
 * The map center longitude.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_MAP_CENTER_LNG = 'maplng';

/**
 * The timeline browser current position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_BROWSER_POSITION = 'tbcurrent';

/**
 * The timeline browser window start position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_BROWSER_START = 'tbstart';

/**
 * The timeline browser window end position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_BROWSER_END = 'tbend';

/**
 * The comparative timeline window start position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINES_START = 'tlstart';

/**
 * The comparative timeline window end position.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINES_END = 'tlend';

/**
 * The cidoc entity being displayed in the sidebar.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY = 'cidoc_entity_id';

/**
 * The timelines being displayed in comparative timline viewer.
 *
 * @type {string}
 */
export const QUERYSTRING_VARIABLE_TIMELINE_ENTITIES = 'timelines';
