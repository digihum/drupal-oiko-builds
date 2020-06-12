import { createStore, applyMiddleware } from 'redux';
import oikoAppReducers from './reducers';
import createHistory from 'history/createBrowserHistory';
import { connectHistory, updateLocation } from './vendor/redux-history';
import thunkMiddleware from 'redux-thunk';
import createLogger from 'redux-logger'
import { connectSidebar } from './sidebar';
import $ from './jquery';
import { appLoadingStart, appLoadingAddToDOM }  from './actions';

/**
 * Create a new instance of an Oiko App.
 *
 * @param $wrapper
 * @returns {oikoApp}
 */
export function createOikoApp() {
  return new oikoApp();
}

class oikoApp {
  constructor() {
    const history = createHistory();

    // Work out what the initial state of our app should be.
    const initialState = oikoAppReducers({}, updateLocation(history.location));

    let middleware = [thunkMiddleware]
    if (process.env.NODE_ENV !== 'production') {
      middleware = [...middleware, createLogger()]
    }

    this.store = createStore(
      oikoAppReducers,
      initialState,
      applyMiddleware(...middleware)
    );

    connectHistory(history, this.store);
    connectSidebar($, this.store);

    this.initFired = false;

    const checkInitState = () => {
      if (this.initFired) {
        return;
      }
      let initDone = true;
      const {appModules} = this.store.getState();

      for (let key of Object.keys(appModules)) {
        initDone = initDone && appModules[key];
      }

      if (initDone) {
        $(window).trigger('oiko.loaded', appModules);
        this.initFired = true;
      }
    };

    // Set up a subscriber to the app stat, on DOM ready.
    $(() => {
      // Check the state immediately
      checkInitState();
      // And then check it on any state change.
      this.store.subscribe(checkInitState)
    });

    this.store.dispatch(appLoadingStart());
  }

  addTo($wrapper) {
    this.$wrapper = $wrapper;
    this.store.dispatch(appLoadingAddToDOM());
    // This should initialise the app, so making sure the correct visualisation is displayed etc.
  }

  getStore() {
    return this.store;
  }


}
