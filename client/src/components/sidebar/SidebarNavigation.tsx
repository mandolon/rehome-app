import React, { useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  LayoutDashboard,
  ChevronDown,
  ChevronRight,
  Home,
  Inbox,
  MessageSquare,
  Users,
  Receipt,
  FileImage,
  FileText,
  ClipboardList,
  Clock,
  FolderOpen,
  FileText as PdfIcon
} from 'lucide-react';
import { cn } from '@/lib/utils';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { useUser } from '@/contexts/UserContext';

interface SidebarNavigationProps {
  isCollapsed: boolean;
  isOpen: boolean;
  onToggle: () => void;
}

const SidebarNavigation = React.memo(({ isCollapsed, isOpen, onToggle }: SidebarNavigationProps) => {
  const navigate = useNavigate();
  const { currentUser, isImpersonating, impersonatedUser } = useUser();

  // Determine if we are in "client mode"
  const clientMode =
    (isImpersonating && impersonatedUser && impersonatedUser.role === 'Client') ||
    (!isImpersonating && currentUser.role === 'Client');

  const handleNavigateClientDashboard = useCallback(() => navigate('/client/dashboard'), [navigate]);
  const handleNavigateClientWhiteboards = useCallback(() => navigate('/client/whiteboards'), [navigate]);
  const handleNavigateHome = useCallback(() => navigate('/'), [navigate]);
  const handleNavigateTasks = useCallback(() => navigate('/tasks'), [navigate]);
  const handleNavigateProjects = useCallback(() => navigate('/projects'), [navigate]);
  const handleNavigateInbox = useCallback(() => navigate('/inbox'), [navigate]);
  const handleNavigateChat = useCallback(() => {}, []);
  const handleNavigateTeams = useCallback(() => navigate('/teams'), [navigate]);
  const handleNavigateInvoices = useCallback(() => navigate('/invoices'), [navigate]);
  const handleNavigateWhiteboards = useCallback(() => navigate('/whiteboards'), [navigate]);
  const handleNavigateTimesheets = useCallback(() => navigate('/timesheets'), [navigate]);
  const handleNavigateWorkRecords = useCallback(() => navigate('/work-records'), [navigate]);
  const handleNavigatePDFViewer = useCallback(() => navigate('/sandbox/pdf-viewer'), [navigate]);

  // Client gets dashboard and Whiteboards
  const clientNavItems = [
    { icon: LayoutDashboard, label: 'Client Dashboard', active: false, onClick: handleNavigateClientDashboard },
    { icon: FileImage, label: 'Whiteboards', active: false, onClick: handleNavigateClientWhiteboards },
  ];

  // Admin/team: full nav, but no Help item (removed!)
  const mainNavItems = useMemo(() => {
    if (clientMode) return clientNavItems;
    return [
      { icon: Home, label: 'Home', active: false, onClick: handleNavigateHome },
      { icon: ClipboardList, label: 'Task Board (Final)', active: false, onClick: handleNavigateTasks },
      { icon: FolderOpen, label: 'Projects', active: false, onClick: handleNavigateProjects },
      { icon: Inbox, label: 'Inbox', active: false, onClick: handleNavigateInbox },
      { icon: MessageSquare, label: 'Chat', active: false, onClick: handleNavigateChat },
      { icon: Users, label: 'Teams', active: false, onClick: handleNavigateTeams },
      { icon: Receipt, label: 'Invoices', active: false, onClick: handleNavigateInvoices },
      { icon: FileImage, label: 'Whiteboards', active: false, onClick: handleNavigateWhiteboards },
      { icon: Clock, label: 'Timesheets', active: false, onClick: handleNavigateTimesheets },
      { icon: FileText, label: 'Work Records', active: false, onClick: handleNavigateWorkRecords },
      { icon: PdfIcon, label: 'PDF Viewer', active: false, onClick: handleNavigatePDFViewer },
      { icon: LayoutDashboard, label: 'Client Dashboard', active: false, onClick: handleNavigateClientDashboard }
    ];
  }, [
    clientMode,
    handleNavigateHome,
    handleNavigateTasks,
    handleNavigateProjects,
    handleNavigateInbox,
    handleNavigateChat,
    handleNavigateTeams,
    handleNavigateInvoices,
    handleNavigateWhiteboards,
    handleNavigateTimesheets,
    handleNavigateWorkRecords,
    handleNavigatePDFViewer,
    handleNavigateClientDashboard,
    handleNavigateClientWhiteboards,
  ]);

  // Collapsed view (clients only show icon for dashboard and new whiteboards)
  if (isCollapsed) {
    return (
      <nav className="flex-1 py-2 px-1 space-y-0">
        {mainNavItems.map((item, index) => (
          <div
            key={index}
            className={cn(
              "flex items-center justify-center p-2 rounded text-sm cursor-pointer transition-colors my-0.5",
              item.active
                ? "bg-sidebar-accent text-sidebar-accent-foreground"
                : "text-sidebar-foreground hover:bg-sidebar-accent/50"
            )}
            onClick={item.onClick}
          >
            <item.icon className="w-4 h-4" />
          </div>
        ))}
      </nav>
    );
  }

  // Show NON-client: collapsible nav
  if (!clientMode) {
    return (
      <Collapsible open={isOpen} onOpenChange={onToggle}>
        <div className="px-2 mb-2">
          {/* Navigation label and collapse arrow remain for non-client users */}
          <CollapsibleTrigger className="flex items-center gap-2 px-2 py-1.5 w-full text-left hover:bg-sidebar-accent/50 rounded">
            {isOpen ? (
              <ChevronDown className="w-3 h-3 text-muted-foreground flex-shrink-0" />
            ) : (
              <ChevronRight className="w-3 h-3 text-muted-foreground flex-shrink-0" />
            )}
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wide truncate">
              Navigation
            </span>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <nav className="space-y-0 mt-2">
              {mainNavItems.map((item, index) => (
                <div
                  key={index}
                  className={cn(
                    "flex items-center gap-3 px-3 py-1.5 rounded text-sm cursor-pointer transition-colors my-0",
                    item.active
                      ? "bg-sidebar-accent text-sidebar-accent-foreground"
                      : "text-sidebar-foreground hover:bg-sidebar-accent/50"
                  )}
                  onClick={item.onClick}
                >
                  <item.icon className="w-4 h-4 flex-shrink-0" />
                  <span className="text-sm truncate">{item.label}</span>
                </div>
              ))}
            </nav>
          </CollapsibleContent>
        </div>
      </Collapsible>
    );
  }

  // Client mode (not collapsed): Dashboard and Whiteboards, no nav label, no collapse arrow
  return (
    <div className="px-2 mb-2">
      <nav className="space-y-0 mt-2">
        {clientNavItems.map((item, index) => (
          <div
            key={index}
            className={cn(
              "flex items-center gap-3 px-3 py-1.5 rounded text-sm cursor-pointer transition-colors my-0",
              item.active
                ? "bg-sidebar-accent text-sidebar-accent-foreground"
                : "text-sidebar-foreground hover:bg-sidebar-accent/50"
            )}
            onClick={item.onClick}
          >
            <item.icon className="w-4 h-4 flex-shrink-0" />
            <span className="text-sm truncate">{item.label}</span>
          </div>
        ))}
      </nav>
    </div>
  );
});

SidebarNavigation.displayName = 'SidebarNavigation';

export default SidebarNavigation;
