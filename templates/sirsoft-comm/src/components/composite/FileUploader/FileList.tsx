

import React from 'react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  rectSortingStrategy,
} from '@dnd-kit/sortable';

import { Div } from '../../basic/Div';
import { Span } from '../../basic/Span';
import { I } from '../../basic/I';

import { SortableThumbnailItem } from './SortableThumbnailItem';
import type { Attachment, PendingFile } from './types';
import { t } from './utils';

export interface FileListProps {
  
  allItems: (Attachment | PendingFile)[];
  
  canAddMore: boolean;
  
  totalCount: number;
  
  maxFiles: number;
  
  maxSize: number;
  
  accept?: string;
  
  onRemove: (item: PendingFile | Attachment) => void;
  
  onRetry: (pendingFile: PendingFile) => void;
  
  onImageClick: (item: PendingFile | Attachment) => void;
  
  onDownload: (item: Attachment) => void;
  
  onDragEnd: (event: DragEndEvent) => void;
  
  onAddClick: () => void;
  
  enablePrimarySelection?: boolean;
  
  primaryFileId?: number | string | null;
  
  onPrimaryChange?: (id: number | string | null) => void;
}

export const FileList: React.FC<FileListProps> = ({
  allItems,
  canAddMore,
  totalCount,
  maxFiles,
  maxSize,
  accept,
  onRemove,
  onRetry,
  onImageClick,
  onDownload,
  onDragEnd,
  onAddClick,
  enablePrimarySelection,
  primaryFileId,
  onPrimaryChange,
}) => {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  return (
    <Div className="p-3">
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext
          items={allItems.map((item) => ('hash' in item ? item.hash : item.id))}
          strategy={rectSortingStrategy}
        >
          <Div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            
            {allItems.map((item) => {
              const isExisting = 'hash' in item;
              
              const isImage = isExisting
                ? (item as Attachment).is_image
                : (item.mime_type?.startsWith('image/') ?? false);

              return (
                <SortableThumbnailItem
                  key={isExisting ? item.hash : item.id}
                  file={item}
                  onRemove={() => onRemove(item)}
                  onRetry={
                    'status' in item && item.status === 'error'
                      ? () => onRetry(item as PendingFile)
                      : undefined
                  }
                  onImageClick={
                    isImage ? () => onImageClick(item) : undefined
                  }
                  onDownload={
                    isExisting ? () => onDownload(item as Attachment) : undefined
                  }
                  isPrimary={enablePrimarySelection && isExisting && ((item as Attachment).hash || (item as Attachment).id) === primaryFileId}
                  onPrimaryClick={
                    enablePrimarySelection && isExisting
                      ? () => onPrimaryChange?.((item as Attachment).hash || (item as Attachment).id)
                      : undefined
                  }
                />
              );
            })}

            
            {canAddMore && (
              <Div
                className="aspect-square flex flex-col items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                onClick={onAddClick}
              >
                <I className="fa-solid fa-plus text-2xl text-gray-400 dark:text-gray-500 mb-1" />
                <Span className="text-xs text-gray-500 dark:text-gray-400">{t('common.add')}</Span>
              </Div>
            )}
          </Div>
        </SortableContext>
      </DndContext>

      
      <Div className="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
        <Span>{t('attachment.attached_count', { count: totalCount, max: maxFiles })}</Span>
        <Span>{t('attachment.max_size', { size: maxSize })}{accept && ` (${accept})`}</Span>
      </Div>
    </Div>
  );
};

export default FileList;