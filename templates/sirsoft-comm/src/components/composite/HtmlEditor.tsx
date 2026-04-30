import React, { useCallback, useState } from 'react';
import { Div } from '../basic/Div';
import { Label } from '../basic/Label';
import { Button } from '../basic/Button';
import { Textarea } from '../basic/Textarea';
import { Input } from '../basic/Input';
import { HtmlContent } from './HtmlContent';


const G7Core = () => (window as any).G7Core;


const t = (key: string, params?: Record<string, string | number>) =>
  G7Core()?.t?.(key, params) ?? key;

export interface HtmlEditorProps {
  
  content?: string;

  
  onChange?: (event: { target: { name: string; value: string } }) => void;

  
  isHtml?: boolean;

  
  onIsHtmlChange?: (event: { target: { name: string; checked: boolean } }) => void;

  
  rows?: number;

  
  placeholder?: string;

  
  label?: string;

  
  showHtmlModeToggle?: boolean;

  
  contentClassName?: string;

  
  purifyConfig?: any;

  
  className?: string;

  
  name?: string;

  
  htmlFieldName?: string;

  
  readOnly?: boolean;
}


export const HtmlEditor: React.FC<HtmlEditorProps> = ({
  content = '',
  onChange,
  isHtml: isHtmlProp = false,
  onIsHtmlChange,
  rows = 15,
  placeholder = '',
  label,
  showHtmlModeToggle = true,
  contentClassName = '',
  purifyConfig,
  className = '',
  name = 'content',
  htmlFieldName = 'content_mode',
  readOnly = false,
}) => {
  
  const [isHtml, setIsHtml] = useState(isHtmlProp);

  
  const [localContent, setLocalContent] = useState(content);

  
  const [previewMode, setPreviewMode] = useState(false);

  
  React.useEffect(() => {
    setIsHtml(isHtmlProp);
  }, [isHtmlProp]);

  
  React.useEffect(() => {
    setLocalContent(content);
  }, [content]);

  
  const handleContentChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const newContent = e.target.value;

    
    setLocalContent(newContent);

    
    if (onChange) {
      const event = G7Core()?.createChangeEvent?.({ value: newContent, name, type: 'textarea' })
        ?? { target: { name, value: newContent } };
      onChange(event);
    }
  }, [onChange, name]);

  
  const handleHtmlModeChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const isHtmlMode = e.target.checked;

    
    setIsHtml(isHtmlMode);

    
    if (!isHtmlMode) {
      setPreviewMode(false);
    }

    
    if (onIsHtmlChange) {
      const event = G7Core()?.createChangeEvent?.({ checked: isHtmlMode, name: htmlFieldName, type: 'checkbox' })
        ?? { target: { name: htmlFieldName, checked: isHtmlMode } };
      onIsHtmlChange(event);
    }
  }, [onIsHtmlChange, htmlFieldName]);

  
  const handlePreviewModeToggle = useCallback(() => {
    setPreviewMode(prev => !prev);
  }, []);

  return (
    <Div className={`space-y-2 ${className}`}>
      <Div className="flex items-center justify-between">

        {label && (
          <Label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
            {label}
          </Label>
        )}

        <Div className="flex items-center gap-3">
          {isHtml && !readOnly && (
            <Button
              type="button"
              onClick={handlePreviewModeToggle}
              className={`px-3 py-1.5 text-xs font-bold rounded-lg focus:outline-none focus:ring-2 ${
                previewMode
                  ? 'text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-600 border border-gray-300 dark:border-gray-500 hover:bg-gray-300 dark:hover:bg-gray-500 focus:ring-gray-400 dark:focus:ring-gray-500'
                  : 'text-blue-600 dark:text-blue-400 bg-white dark:bg-gray-700 border border-blue-300 dark:border-blue-600 hover:bg-blue-50 dark:hover:bg-gray-600 focus:ring-blue-500 dark:focus:ring-blue-600'
              }`}
            >
              {previewMode ? t('common.preview_off') : t('common.preview')}
            </Button>
          )}

          {showHtmlModeToggle && (
            <Label className="flex items-center gap-2 p-2 cursor-pointer">
              <Input
                type="checkbox"
                name={htmlFieldName}
                checked={isHtml}
                onChange={handleHtmlModeChange}
                disabled={readOnly}
                className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
              />
              <Div className="text-sm font-medium text-gray-700 dark:text-gray-300">
                {t('common.html_mode')}
              </Div>
            </Label>
          )}
        </Div>
      </Div>

      {!previewMode && (
        <Textarea
          name={name}
          value={localContent}
          onChange={handleContentChange}
          placeholder={placeholder}
          rows={rows}
          readOnly={readOnly}
          className={`block w-full rounded-lg border px-3 py-2 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 disabled:opacity-50 disabled:cursor-not-allowed ${
            isHtml
              ? 'font-mono bg-white dark:bg-gray-800 border-blue-300 dark:border-blue-600 text-gray-800 dark:text-gray-200 focus:border-blue-500 focus:ring-blue-500'
              : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-blue-500'
          }`}
        />
      )}

      {previewMode && (
        <Div className="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 min-h-[400px]">
          <HtmlContent
            content={localContent}
            isHtml={true}
            className={contentClassName || 'prose dark:prose-invert max-w-none text-gray-900 dark:text-gray-100'}
            purifyConfig={purifyConfig}
          />
        </Div>
      )}

    </Div>
  );
};
