import { setThemeHandler, initThemeHandler } from './setThemeHandler';
/**
 * sirsoft-comm 템플릿의 모든 커스텀 핸들러
 *
 * 네이밍 규칙: 'sirsoft-comm.[핸들러명]'
 *
 * @example
 * // 레이아웃 JSON에서 사용
 * {
 *   "handler": "custom",
 *   "name": "addSelectedItemIfComplete",
 *   "params": { ... }
 * }
 *
 * // 또는 풀네임으로
 * {
 *   "handler": "sirsoft-comm.addSelectedItemIfComplete",
 *   "params": { ... }
 * }
 */
export declare const handlers: {
    setTheme: typeof setThemeHandler;
    initTheme: typeof initThemeHandler;
};
/**
 * 핸들러 맵 (handlerMap alias)
 *
 * index.ts에서 import할 때 사용
 */
export declare const handlerMap: {
    setTheme: typeof setThemeHandler;
    initTheme: typeof initThemeHandler;
};
/**
 * 핸들러 타입 정의 (TypeScript 자동완성용)
 */
export type SirsoftCommHandlers = typeof handlers;
export { setThemeHandler, initThemeHandler, };
