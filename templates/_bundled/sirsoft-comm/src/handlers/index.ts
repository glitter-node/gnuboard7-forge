


import { setThemeHandler, initThemeHandler } from './setThemeHandler';





export const handlers = {
  setTheme: setThemeHandler,
  initTheme: initThemeHandler,
};


export const handlerMap = handlers;


export type SirsoftCommHandlers = typeof handlers;


export {
  setThemeHandler,
  initThemeHandler,
};
