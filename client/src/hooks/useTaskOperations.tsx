import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Task } from '@/types/task';
import { fetchAllTasks, createTask, updateTask, deleteTask } from '@/data/api';
import { useWebSocket } from './useWebSocket';

export function useTaskOperations() {
  const [customTasks, setCustomTasks] = useState<Task[]>([]);
  const [archivedTasks, setArchivedTasks] = useState<Task[]>([]);
  const [refreshTrigger, setRefreshTrigger] = useState(0);
  const queryClient = useQueryClient();

  // WebSocket connection for real-time updates
  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  // In development, always use localhost:5000, in production use current host
  const isDevelopment = import.meta.env.DEV;
  const wsUrl = isDevelopment 
    ? `${protocol}//localhost:5000/ws`
    : `${protocol}//${window.location.host}/ws`;
  
  const handleWebSocketMessage = useCallback((message: { event: string; data: any }) => {
    console.log('WebSocket message received:', message);
    
    switch (message.event) {
      case 'task_created':
        setCustomTasks(prev => [...prev, message.data]);
        queryClient.invalidateQueries({ queryKey: ['api-tasks'] });
        break;
      
      case 'task_updated':
        setCustomTasks(prev => 
          prev.map(task => 
            task.taskId === message.data.taskId ? message.data : task
          )
        );
        queryClient.invalidateQueries({ queryKey: ['api-tasks'] });
        break;
      
      case 'task_deleted':
        setCustomTasks(prev => 
          prev.filter(task => task.taskId !== message.data.taskId)
        );
        queryClient.invalidateQueries({ queryKey: ['api-tasks'] });
        break;
      
      default:
        console.log('Unknown WebSocket event:', message.event);
    }
  }, [queryClient]);

  const { isConnected } = useWebSocket(wsUrl, handleWebSocketMessage);

  // Fetch tasks query with direct API call
  const { data: tasks = [], isLoading } = useQuery<Task[]>({
    queryKey: ['api-tasks'],
    queryFn: async () => {
      console.log('Making direct API call for tasks in useTaskOperations');
      const response = await fetch('/api/tasks');
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const data = await response.json();
      console.log('Tasks loaded in useTaskOperations:', data.length);
      return data;
    },
    staleTime: 30 * 60 * 1000, // 30 minutes
    gcTime: 60 * 60 * 1000, // 1 hour (formerly cacheTime)
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    refetchInterval: false,
    refetchOnMount: false,
    retry: false,
  });

  // Update local state when tasks change
  useEffect(() => {
    const activeTasks = tasks.filter((task: Task) => !task.archived);
    const archived = tasks.filter((task: Task) => task.archived);
    setCustomTasks(activeTasks);
    setArchivedTasks(archived);
  }, [tasks]);

  // Create task mutation with direct API call
  const createTaskMutation = useMutation({
    mutationFn: async (newTask: any) => {
      const response = await fetch('/api/tasks', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(newTask),
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['api-tasks'] });
    },
  });

  // Update task mutation with direct API call
  const updateTaskMutation = useMutation({
    mutationFn: async ({ taskId, updates }: { taskId: string; updates: Partial<Task> }) => {
      const response = await fetch(`/api/tasks/${taskId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(updates),
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['api-tasks'] });
    },
  });

  // Delete task mutation with direct API call
  const deleteTaskMutation = useMutation({
    mutationFn: async (taskId: string) => {
      const response = await fetch(`/api/tasks/${taskId}`, {
        method: 'DELETE',
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return taskId;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['api-tasks'] });
    },
  });

  const createTaskHandler = useCallback((taskData: any) => {
    createTaskMutation.mutate(taskData);
  }, [createTaskMutation.mutate]);

  const updateTaskById = useCallback((taskId: number, updates: Partial<Task>) => {
    const task = tasks.find((t: Task) => t.id === taskId);
    if (task) {
      updateTaskMutation.mutate({ taskId: task.taskId, updates });
    }
  }, [tasks, updateTaskMutation.mutate]);

  const deleteTaskHandler = useCallback(async (taskId: number): Promise<void> => {
    const task = tasks.find((t: Task) => t.id === taskId);
    if (task) {
      deleteTaskMutation.mutate(task.taskId);
    }
  }, [tasks, deleteTaskMutation.mutate]);

  const restoreDeletedTask = useCallback((taskId: number) => {
    // Implementation for restoring deleted tasks
    console.log('Restore task:', taskId);
  }, []);

  const archiveTask = useCallback((taskId: number) => {
    updateTaskById(taskId, { archived: true });
  }, [updateTaskById]);

  const navigateToTask = useCallback((task: Task) => {
    console.log('Navigate to task:', task);
  }, []);

  const getTasksByStatus = useCallback((status: string) => {
    return tasks.filter((task: Task) => task.status === status && !task.archived);
  }, [tasks]);

  const getAllTasks = useCallback(() => {
    return tasks.filter((task: Task) => !task.archived);
  }, [tasks]);

  const triggerRefresh = useCallback(() => {
    setRefreshTrigger(prev => prev + 1);
    queryClient.invalidateQueries({ queryKey: ['/api/tasks'] });
  }, [queryClient]);

  return {
    customTasks,
    archivedTasks,
    refreshTrigger,
    isLoading,
    isConnected,
    createTask: createTaskHandler,
    updateTaskById,
    deleteTask: deleteTaskHandler,
    restoreDeletedTask,
    archiveTask,
    navigateToTask,
    getTasksByStatus,
    getAllTasks,
    triggerRefresh,
  };
}