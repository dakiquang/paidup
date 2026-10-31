import { router, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from "react-native";
import { InvoiceRow } from "../components/InvoiceRow";
import { ApiError, fetchInvoices } from "../lib/api";
import { readToken } from "../lib/session";
import type { Invoice } from "../lib/types";

export default function InvoicesScreen() {
  const [invoices, setInvoices] = useState<Invoice[] | null>(null);
  const [error, setError] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!(await readToken())) {
      router.replace("/login");
      return;
    }

    try {
      setInvoices(await fetchInvoices());
      setError(false);
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) router.replace("/login");
      else setError(true);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  async function refresh() {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }

  if (error && !invoices) {
    return (
      <View style={styles.center}>
        <Text>Couldn&apos;t load your invoices.</Text>
        <Pressable accessibilityRole="button" onPress={load} style={styles.retry}>
          <Text style={styles.retryText}>Try again</Text>
        </Pressable>
      </View>
    );
  }

  if (!invoices) {
    return (
      <View style={styles.center}>
        <ActivityIndicator accessibilityLabel="Loading" />
      </View>
    );
  }

  return (
    <FlatList
      style={styles.list}
      data={invoices}
      keyExtractor={(invoice) => String(invoice.id)}
      refreshing={refreshing}
      onRefresh={refresh}
      ListEmptyComponent={<Text style={styles.empty}>No invoices yet.</Text>}
      renderItem={({ item }) => (
        <InvoiceRow invoice={item} onPress={() => router.push(`/invoices/${item.id}`)} />
      )}
    />
  );
}

const styles = StyleSheet.create({
  list: { backgroundColor: "#fff" },
  center: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12, backgroundColor: "#fff" },
  retry: { minHeight: 44, justifyContent: "center", paddingHorizontal: 16 },
  retryText: { fontWeight: "600", textDecorationLine: "underline" },
  empty: { padding: 24, textAlign: "center", color: "#52525b" },
});
