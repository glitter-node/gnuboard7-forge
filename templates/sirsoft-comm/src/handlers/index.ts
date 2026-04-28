/**
 * sirsoft-comm 템플릿 핸들러 등록
 *
 * 이 파일에서 모든 커스텀 핸들러를 export합니다.
 * G7Core에서 자동으로 로드하여 사용할 수 있습니다.
 */

// 테마 관련 핸들러
import { setThemeHandler, initThemeHandler } from './setThemeHandler';

// 언어 관련 핸들러는 엔진 레벨(ActionDispatcher)에서 처리
// setLocale 핸들러는 ActionDispatcher에 빌트인으로 등록되어 있음

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
export const handlers = {
  setTheme: setThemeHandler,
  initTheme: initThemeHandler,
};

/**
 * 핸들러 맵 (handlerMap alias)
 *
 * index.ts에서 import할 때 사용
 */
export const handlerMap = handlers;

/**
 * 핸들러 타입 정의 (TypeScript 자동완성용)
 */
export type SirsoftCommHandlers = typeof handlers;

// 개별 핸들러 export (직접 import용)
export {
  setThemeHandler,
  initThemeHandler,
};
