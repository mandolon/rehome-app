
import React, { useCallback, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Task, TaskGroup, TaskUser } from '@/types/task';
import { useUser } from '@/contexts/UserContext';
import { useRealtimeTasks } from './useRealtimeTasks';
import { fetchAllTasks, createTask, updateTask, deleteTask } from '@/data/api';
import { nanoid } from "nanoid";

// New: helper to deep copy and update list
function updateTaskInList(tasks: Task[], taskId: string, updater: (t: Task) => Task) {
  return tasks.map(t => t.taskId === taskId ? updater(t) : t);
}

export const useTaskBoard = () => {
  const navigate = useNavigate();
  const { currentUser } = useUser();
  const queryClient = useQueryClient();
  
  // Enable real-time updates
  useRealtimeTasks();
  
  // Completely isolated query with unique key to avoid conflicts
  const fetchTasksDirectly = React.useCallback(async () => {
    console.log('Making direct API call for tasks');
    const response = await fetch('/api/tasks');
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const data = await response.json();
    console.log('Tasks loaded:', data.length);
    return data;
  }, []);

  const { data: tasks = [], isLoading: loading, error } = useQuery({
    queryKey: ['task-board-data'],
    queryFn: fetchTasksDirectly,
    retry: false,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    refetchInterval: false,
    refetchOnMount: false,
    staleTime: 30 * 60 * 1000, // 30 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
  });

  // Debug the query state
  React.useEffect(() => {
    console.log('Query state - Loading:', loading, 'Error:', error, 'Tasks count:', tasks?.length);
    if (tasks?.length > 0) {
      console.log('First task:', tasks[0]);
    }
  }, [loading, error, tasks]);

  // Dialog/quick add state
  const [isTaskDialogOpen, setIsTaskDialogOpen] = useState(false);
  const [showQuickAdd, setShowQuickAdd] = useState<string | null>(null);
  const [refreshTrigger, setRefreshTrigger] = useState(0);

  // Task groups powered by API - memoized to prevent unnecessary recalculations
  const taskGroups = React.useMemo((): TaskGroup[] => {
    if (!tasks || !Array.isArray(tasks)) {
      return [
        { title: "TASK/ REDLINE", count: 0, color: "bg-[#c62a2f]", status: "redline", tasks: [] },
        { title: "PROGRESS/ UPDATE", count: 0, color: "bg-blue-500", status: "progress", tasks: [] },
        { title: "COMPLETED", count: 0, color: "bg-green-500", status: "completed", tasks: [] }
      ];
    }
    
    const centralizedRedline = tasks.filter((task: any) => task.status === 'redline' && !task.archived && !task.deletedAt);
    const centralizedProgress = tasks.filter((task: any) => task.status === 'progress' && !task.archived && !task.deletedAt);
    const centralizedCompleted = tasks.filter((task: any) => task.status === 'completed' && !task.deletedAt);

    const taskGroups: TaskGroup[] = [
      {
        title: "TASK/ REDLINE",
        count: centralizedRedline.length,
        color: "bg-[#c62a2f]",
        status: "redline",
        tasks: centralizedRedline
      },
      {
        title: "PROGRESS/ UPDATE",
        count: centralizedProgress.length,
        color: "bg-blue-500",
        status: "progress",
        tasks: centralizedProgress
      },
      {
        title: "COMPLETED",
        count: centralizedCompleted.length,
        color: "bg-green-500",
        status: "completed",
        tasks: centralizedCompleted
      }
    ];
    return taskGroups;
  }, [tasks, refreshTrigger]);

  // Expose taskGroups directly instead of through a function
  const getTaskGroups = useCallback(() => taskGroups, [taskGroups]);

  // Generate a new taskId for every task insert
  const generateTaskId = () => "T" + Math.floor(Math.random() * 100000).toString().padStart(4, "0");

  // Create task mutation with direct API call and optimistic updates
  const createTaskMutation = useMutation({
    mutationFn: async (newTask: any) => {
      console.log('Creating task:', newTask);
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
      const result = await response.json();
      console.log('Task created successfully:', result);
      return result;
    },
    onMutate: async (newTask) => {
      // Cancel any outgoing refetches
      await queryClient.cancelQueries({ queryKey: ['task-board-data'] });
      
      // Snapshot the previous value
      const previousTasks = queryClient.getQueryData(['task-board-data']);
      
      // Optimistically update to show the new task immediately
      queryClient.setQueryData(['task-board-data'], (old: Task[] = []) => [
        ...old,
        {
          ...newTask,
          id: Date.now(), // Temporary ID until server responds
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
          archived: false,
          deletedAt: null,
          deletedBy: null,
          workRecord: false,
        }
      ]);
      
      return { previousTasks };
    },
    onError: (err, newTask, context) => {
      // If the mutation fails, roll back
      queryClient.setQueryData(['task-board-data'], context?.previousTasks);
    },
    onSettled: () => {
      // Always refetch after error or success
      queryClient.invalidateQueries({ queryKey: ['task-board-data'] });
      setRefreshTrigger(prev => prev + 1);
    },
  });

  const handleCreateTask = useCallback(
    async (newTask: any) => {
      const taskId = newTask.taskId ?? generateTaskId();
      createTaskMutation.mutate({
        ...newTask,
        taskId,
        createdBy: currentUser?.name ?? currentUser?.email ?? "Unknown",
      });
      setIsTaskDialogOpen(false);
    },
    [currentUser, createTaskMutation]
  );

  const handleQuickAddSave = useCallback(
    async (taskData: any) => {
      const taskId = taskData.taskId ?? generateTaskId();
      createTaskMutation.mutate({
        ...taskData,
        taskId,
        createdBy: currentUser?.name ?? currentUser?.email ?? "Unknown",
      });
      setShowQuickAdd(null);
    },
    [currentUser, createTaskMutation]
  );

  const handleTaskClick = (task: Task) => {
    navigate(`/task/${task.taskId}`);
  };

  // Update task mutation with direct API call
  const updateTaskMutation = useMutation({
    mutationFn: async ({ taskId, updates }: { taskId: string; updates: Partial<Task> }) => {
      console.log('Updating task:', taskId, updates);
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
      const result = await response.json();
      console.log('Task updated successfully:', result);
      return result;
    },
    onSuccess: () => {
      console.log('Update mutation success, invalidating cache');
      queryClient.invalidateQueries({ queryKey: ['task-board-data'] });
      setRefreshTrigger(prev => prev + 1);
    },
  });

  // Delete task mutation with direct API call
  const deleteTaskMutation = useMutation({
    mutationFn: async (taskId: string) => {
      console.log('Deleting task:', taskId);
      const response = await fetch(`/api/tasks/${taskId}`, {
        method: 'DELETE',
      });
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      console.log('Task deleted successfully:', taskId);
      return taskId;
    },
    onSuccess: (deletedTaskId) => {
      console.log('Delete mutation success, invalidating cache');
      queryClient.invalidateQueries({ queryKey: ['task-board-data'] });
      setRefreshTrigger(prev => prev + 1);
    },
  });

  const handleTaskArchive = async (taskId: number) => {
    const task = tasks.find((t: any) => t.id === taskId);
    if (task) {
      updateTaskMutation.mutate({ taskId: task.taskId, updates: { archived: true } });
    }
  };

  const handleTaskDeleted = async (taskId: string) => {
    const task = tasks.find((t: any) => t.taskId === taskId);
    if (task) {
      deleteTaskMutation.mutate(task.taskId);
    }
  };

  // Assignment handlers using API mutations
  const assignPerson = async (taskId: string, person: TaskUser) => {
    updateTaskMutation.mutate({ taskId, updates: { assignee: person } });
  };
  
  const removeAssignee = async (taskId: string) => {
    updateTaskMutation.mutate({ taskId, updates: { assignee: null } });
  };
  
  const addCollaborator = async (taskId: string, person: TaskUser) => {
    const task = tasks.find((t: any) => t.taskId === taskId);
    const collabs = (task?.collaborators ?? []).slice();
    if (!collabs.find((c: any) => c.id === person.id)) {
      collabs.push(person);
    }
    updateTaskMutation.mutate({ taskId, updates: { collaborators: collabs } });
  };
  
  const removeCollaborator = async (taskId: string, collaboratorIndex: number) => {
    const task = tasks.find((t: any) => t.taskId === taskId);
    const collabs = (task?.collaborators ?? []).slice();
    collabs.splice(collaboratorIndex, 1);
    updateTaskMutation.mutate({ taskId, updates: { collaborators: collabs } });
  };

  return {
    isTaskDialogOpen,
    setIsTaskDialogOpen,
    showQuickAdd,
    setShowQuickAdd,
    refreshTrigger,
    taskGroups,
    getTaskGroups,
    handleCreateTask,
    handleQuickAddSave,
    handleTaskClick,
    handleTaskArchive,
    handleTaskDeleted,
    // Assignment handlers for Supabase tasks only:
    assignPerson,
    removeAssignee,
    addCollaborator,
    removeCollaborator,
    tasks, // expose tasks for dependency tracking
    supabaseTasks: tasks, // expose realtime tasks for detail page
    supabaseTasksLoading: loading,
  };
};
