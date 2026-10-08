import AdminMapPage, {
  RouterMarker,
  Odp,
  OnuMarker,
  CustomerMarker,
  SimpleCustomer,
  ConnectionLine,
} from "@/pages/Admin/Map"
import { PageProps } from "@/types"

export default function CollectorMapPage(props: PageProps<{
  routers?: RouterMarker[]
  odps?: Odp[]
  onusMarkers?: OnuMarker[]
  odpMarkers?: Odp[]
  customerMarkers?: CustomerMarker[]
  allCustomers?: SimpleCustomer[]
  connections?: ConnectionLine[]
  readOnly?: boolean
  role?: "admin" | "technician" | "collector"
}>) {
  return (
    <AdminMapPage
      {...props}
      odps={props.odps || props.odpMarkers || []}
      onusMarkers={props.onusMarkers || []}
      readOnly={true}
      role="collector"
    />
  )
}
