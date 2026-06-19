import * as Icons from 'lucide-react';

// Tamaños estándar del sistema de iconos Tenri ERP.
// strokeWidth único: 1.75 en todo el sistema.
const TAMANIOS = { sm: 16, md: 20, lg: 24, xl: 32 };
const STROKE = 1.75;

/**
 * Envoltorio de lucide-react con tamaños y strokeWidth estandarizados.
 *
 * Uso:
 *   <Icono nombre="Search" tamanio="md" className="text-slate-600" />
 *
 * Si se necesita un icono directamente:
 *   import { Search, Trash2, Pencil } from 'lucide-react';
 */
export function Icono({ nombre, tamanio = 'md', className = '', ...props }) {
    const IconoComponente = Icons[nombre];
    if (!IconoComponente) return null;

    return (
        <IconoComponente
            size={TAMANIOS[tamanio] ?? TAMANIOS.md}
            strokeWidth={STROKE}
            className={className}
            {...props}
        />
    );
}

// Re-exporta los iconos más usados en el sistema para imports centralizados.
export {
    Search,
    Plus,
    Pencil,
    Trash2,
    X,
    Check,
    ChevronDown,
    ChevronUp,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Eye,
    EyeOff,
    Download,
    Upload,
    FileText,
    FileSpreadsheet,
    Printer,
    RefreshCw,
    Filter,
    AlertCircle,
    AlertTriangle,
    Info,
    CheckCircle,
    XCircle,
    Clock,
    Calendar,
    Building2,
    User,
    Users,
    LogOut,
    Settings,
    Menu,
    Home,
    BarChart2,
    TrendingUp,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    ArrowDownRight,
    Lock,
    Unlock,
    Send,
    RotateCcw,
    Copy,
    Clipboard,
    Link,
    ExternalLink,
    Loader2,
    Save,
    Edit,
    List,
    Grid,
    SlidersHorizontal,
    Package,
    Warehouse,
    DollarSign,
    CreditCard,
    Receipt,
    Bell,
    Mail,
    Phone,
    MapPin,
    Globe,
    Star,
    Bookmark,
    Tag,
    Layers,
    Zap,
    Shield,
    HelpCircle,
    BookOpen,
    FileSearch,
    FilePlus,
    FileMinus,
    FolderOpen,
    Database,
    Activity,
    PieChart,
    LineChart,
    Scale,
    Wallet,
    Banknote,
    Landmark,
    CircleDot,
    Circle,
    MoreHorizontal,
    MoreVertical,
    Maximize2,
    Minimize2,
    ChevronFirst,
    ChevronLast,
} from 'lucide-react';
