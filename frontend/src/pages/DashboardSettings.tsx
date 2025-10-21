import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export default function DashboardSettings() {
  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-3xl font-semibold text-primary">Settings</h1>
        <p className="text-sm text-primary/80">
          Configure ranch tools, automation, and booking preferences.
        </p>
      </header>
      <Card>
        <CardHeader>
          <CardTitle>Coming soon</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-primary/70">
            Settings for additional Silent Oak Ranch modules will appear here. Let us know what you would like
            to customise next!
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
