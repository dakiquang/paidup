import { Stack } from "expo-router";
import { StatusBar } from "expo-status-bar";

export default function Layout() {
  return (
    <>
      <StatusBar style="dark" />
      <Stack>
        <Stack.Screen name="index" options={{ title: "Invoices" }} />
        <Stack.Screen name="login" options={{ headerShown: false }} />
        <Stack.Screen name="invoices/[id]" options={{ title: "Invoice" }} />
      </Stack>
    </>
  );
}
