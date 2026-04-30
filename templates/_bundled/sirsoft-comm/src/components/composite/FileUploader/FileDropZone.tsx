

import React from 'react';

import { Div } from '../../basic/Div';
import { P } from '../../basic/P';
import { I } from '../../basic/I';

import { t } from './utils';

export interface FileDropZoneProps {
  
  isDragOver: boolean;
  
  canAddMore: boolean;
  
  onFiles: (files: FileList) => void;
  
  setIsDragOver: (value: boolean) => void;
  
  inputRef: React.RefObject<HTMLInputElement | null>;
  
  accept?: string;
  
  maxFiles: number;
  
  maxSize: number;
  
  children?: React.ReactNode;
}

export const FileDropZone: React.FC<FileDropZoneProps> = ({
  isDragOver,
  canAddMore,
  onFiles,
  setIsDragOver,
  inputRef,
  accept,
  maxFiles,
  maxSize,
  children,
}) => {
  return (
    <Div
      className={`
        relative border-2 border-dashed rounded-lg transition-colors
        ${
          isDragOver
            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
            : 'border-gray-300 dark:border-gray-600'
        }
      `}
      onDragOver={(e) => {
        e.preventDefault();
        if (canAddMore) setIsDragOver(true);
      }}
      onDragLeave={() => setIsDragOver(false)}
      onDrop={(e) => {
        e.preventDefault();
        setIsDragOver(false);
        if (canAddMore) {
          onFiles(e.dataTransfer.files);
        }
      }}
    >
      <input
        ref={inputRef}
        type="file"
        multiple
        accept={accept}
        onChange={(e) => e.target.files && onFiles(e.target.files)}
        className="hidden"
      />

      {/* 파일이 없을 때: 빈 드롭존 */}
      {!children && (
        <Div
          className="p-8 text-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
          onClick={() => inputRef.current?.click()}
        >
          <I className="fa-solid fa-cloud-arrow-up text-4xl text-gray-400 dark:text-gray-500 mb-4" />
          <P className="text-sm text-gray-600 dark:text-gray-400">
            {t('attachment.drop_or_click')}
          </P>
          <P className="text-xs text-gray-500 dark:text-gray-500 mt-2">
            {t('attachment.upload_limit', { maxFiles, maxSize })}
            {accept && ` (${accept})`}
          </P>
        </Div>
      )}

      {/* 파일이 있을 때: 자식 요소 렌더링 */}
      {children}
    </Div>
  );
};

export default FileDropZone;