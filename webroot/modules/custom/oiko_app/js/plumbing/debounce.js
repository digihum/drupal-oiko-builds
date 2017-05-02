// https://gist.githubusercontent.com/beaucharman/1f93fdd7c72860736643d1ab274fee1a/raw/469d1b473c35637de33436995e87a3f25f39e233/debounce.js
export default function debounce(callback, wait, context = this) {
  let timeout = null
  let callbackArgs = null

  const later = () => callback.apply(context, callbackArgs)

  return function() {
    callbackArgs = arguments
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

/**
 * Normal event
 * event      | |      |
 * time     ----------------
 * callback   | |      |
 *
 * Call log only when it's been 100ms since the last sroll
 * scroll     | |      |
 * time     ----------------
 * callback         |      |
 *              |100|  |100|
 */

/* usage
 const handleScroll = debounce((e) => {
 console.log('Window scrolled.')
 }, 100)

 window.addEventListener('scroll', handleScroll)
 */