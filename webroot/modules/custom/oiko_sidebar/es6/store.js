import { createStore, applyMiddleware } from 'redux';
import oikoAppReducers from './reducers';
import createHistory from 'history/createBrowserHistory';
import { connectHistory, updateLocation } from './redux-history';
import thunkMiddleware from 'redux-thunk';
import createLogger from 'redux-logger'
import { connectSidebar } from './sidebar';


/**
 * Create a new instance of an Oiko App.
 *
 * @param jQuery
 * @returns {oikoApp}
 */
export function createOikoApp(jQuery) {
  return new oikoApp(jQuery);
}

class oikoApp {
  constructor(jQuery) {
    this.$  = jQuery;
    const loggerMiddleware = createLogger();

    // Create a Redux store holding the state of your app.
    // Its API is { subscribe, dispatch, getState }.
    this.store = createStore(
      oikoAppReducers,
      applyMiddleware(
        thunkMiddleware,
        loggerMiddleware
      )
    );

    const history = createHistory();
    connectHistory(history, this.store);
    connectSidebar(this.$, this.store);

    // Fetch the location, on window load, we'll put it back into our state.
    this.initialLocation = history.location;

    this.store.dispatch(updateLocation(history.location));

    this.initFired = false;

    const checkInitState = () => {
      if (this.initFired) {
        return;
      }
      let initDone = true;
      const { appModules } = this.store.getState();

      for (let key of Object.keys(appModules)) {
        initDone = initDone && appModules[key];
      }

      if (initDone) {
        this.$(window).trigger('oiko.loaded', appModules);
        this.initFired = true;
      }
    };

    // Set up a subscriber to the app stat, on DOM ready.
    this.$(() => {
      // Check the state immediately
      checkInitState();
      // And then check it on any state change.
      this.store.subscribe(checkInitState)
    });
  }

  getStore() {
    return this.store;
  }


}
