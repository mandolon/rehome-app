
import React, { useState, useMemo, useCallback } from 'react';
import { Folder, MoreHorizontal, Plus } from 'lucide-react';
import SidebarProjectSection from './SidebarProjectSection';
import CreateProjectDialog from './CreateProjectDialog';
import { useQuery } from '@tanstack/react-query';

interface Workspace {
  name: string;
  active: boolean;
  locked: boolean;
}

interface SidebarWorkspaceProps {
  workspace: Workspace;
  refreshTrigger?: number;
}

const SidebarWorkspace = React.memo(({ workspace, refreshTrigger }: SidebarWorkspaceProps) => {
  const [openSections, setOpenSections] = useState({
    inProgress: true,
    onHold: false,
    completed: false,
  });
  const [allSectionsCollapsed, setAllSectionsCollapsed] = useState(false);

  const toggleSection = useCallback((section: keyof typeof openSections) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  }, []);

  const handleWorkspaceClick = useCallback(() => {
    // Toggle master collapse state
    setAllSectionsCollapsed(prev => {
      const newCollapsedState = !prev;
      
      if (newCollapsedState) {
        // Collapse all sections
        setOpenSections({
          inProgress: false,
          onHold: false,
          completed: false,
        });
      } else {
        // Restore sections to their previous state or default open state
        setOpenSections({
          inProgress: true,
          onHold: false,
          completed: false,
        });
      }
      
      return newCollapsedState;
    });
  }, []);

  // Fetch real project data from API
  const { data: projects = [] } = useQuery({
    queryKey: ['/api/projects'],
    queryFn: async () => {
      const response = await fetch('/api/projects');
      if (!response.ok) {
        throw new Error('Failed to fetch projects');
      }
      return response.json();
    },
    staleTime: 30 * 60 * 1000, // 30 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    refetchInterval: false,
    refetchOnMount: false,
    retry: false,
  });

  // Group projects by status
  const inProgressProjects = useMemo(() => 
    projects.filter((p: any) => p.status === 'in_progress').map((p: any) => p.title), 
    [projects, refreshTrigger]
  );
  const onHoldProjects = useMemo(() => 
    projects.filter((p: any) => p.status === 'on_hold').map((p: any) => p.title), 
    [projects, refreshTrigger]
  );
  const completedProjects = useMemo(() => 
    projects.filter((p: any) => p.status === 'completed').map((p: any) => p.title), 
    [projects, refreshTrigger]
  );

  const toggleInProgress = useCallback(() => toggleSection('inProgress'), [toggleSection]);
  const toggleOnHold = useCallback(() => toggleSection('onHold'), [toggleSection]);
  const toggleCompleted = useCallback(() => toggleSection('completed'), [toggleSection]);

  return (
    <div>
      <div 
        onClick={handleWorkspaceClick}
        className="flex items-center gap-2 px-2 py-1.5 rounded text-sm cursor-pointer hover:bg-sidebar-accent/50"
      >
        <Folder className="w-4 h-4 text-muted-foreground flex-shrink-0" />
        <span className="truncate flex-1 text-sm">{workspace.name}</span>
        {workspace.locked && <div className="w-3 h-3 text-muted-foreground text-xs flex-shrink-0">🔒</div>}
        <div className="flex items-center gap-1 flex-shrink-0">
          <MoreHorizontal className="w-3 h-3 text-muted-foreground hover:text-foreground" />
          <CreateProjectDialog>
            <button className="p-0.5 hover:bg-sidebar-accent rounded transition-colors">
              <Plus className="w-3 h-3 text-muted-foreground hover:text-foreground" />
            </button>
          </CreateProjectDialog>
        </div>
      </div>

      <SidebarProjectSection
        title="in Progress"
        projects={inProgressProjects}
        isOpen={openSections.inProgress}
        onToggle={toggleInProgress}
        refreshTrigger={refreshTrigger}
      />

      <SidebarProjectSection
        title="on Hold"
        projects={onHoldProjects}
        isOpen={openSections.onHold}
        onToggle={toggleOnHold}
        refreshTrigger={refreshTrigger}
      />

      <SidebarProjectSection
        title="Completed"
        projects={completedProjects}
        isOpen={openSections.completed}
        onToggle={toggleCompleted}
        refreshTrigger={refreshTrigger}
      />
    </div>
  );
});

SidebarWorkspace.displayName = 'SidebarWorkspace';

export default SidebarWorkspace;
